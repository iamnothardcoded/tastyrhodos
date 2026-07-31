<?php

declare(strict_types=1);

namespace Jamasa\Core\Http\Controllers;

use Carbon\Carbon;
use Igniter\Local\Models\Location;
use Igniter\Local\Models\LocationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Ordering settings API for operator tooling (print-server, staff app).
 *
 * Pauses/resumes online ordering by writing a `paused` flag to a dedicated
 * `jamasa_ordering_state` settings row. The actual "closed" behaviour is applied
 * by the PauseWorkingSchedule listener, which — while that flag is set — forces
 * the location's working schedule closed via a WorkingSchedule exception.
 *
 * Why the flag + schedule exception (and NOT disabling order types or the
 * location):
 *  - Disabling BOTH order types (`{type}.is_enabled = 0`) sends TI's
 *    FulfillmentModal into an infinite redirect loop (no valid order type).
 *  - Disabling the location (`location_status = 0`) 500s (issue #1184).
 *  - Forcing the schedule closed leaves the location + order types ENABLED and
 *    reproduces TI's native nightly "CLOSED" state: menu browses, checkout is
 *    gated, nothing goes null. See PauseWorkingSchedule for the mechanism.
 *
 * State lives in one row so pause/resume are idempotent and a printer resume
 * cannot clobber a manual admin pause.
 */
class OrderingSettingsController extends Controller
{
    /** Order types we gate. */
    protected array $orderTypes = ['collection', 'delivery'];

    /** The settings row that holds our pause state. */
    protected string $stateItem = 'jamasa_ordering_state';

    public function show(Request $request): JsonResponse
    {
        if (!$location = $this->resolveLocation($request)) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        return response()->json($this->statePayload($location));
    }

    public function update(Request $request): JsonResponse
    {
        if (!$location = $this->resolveLocation($request)) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        $action = (string) $request->input('action');

        // Audit trail for state-changing actions: 2026-07-31 dev's manual_accept
        // flipped with no identifiable actor (three UIs share one bearer token,
        // container recreate had eaten the HTTP logs). One line per mutation —
        // heartbeat excluded (every reminder tick would spam the log).
        if ($action !== 'heartbeat') {
            logger()->info(sprintf(
                'famedo ordering-settings: action=%s payload=%s ip=%s ua=%s',
                $action,
                json_encode($request->except(['action'])),
                (string) $request->ip(),
                substr((string) $request->userAgent(), 0, 120),
            ));
        }

        switch ($action) {
            case 'pause':
                $this->pause(
                    $location,
                    $this->normalizeSource((string) $request->input('source', 'api')),
                    $request->input('reason'),
                );
                break;

            case 'resume':
                $this->resume(
                    $location,
                    $this->normalizeSource((string) $request->input('source', 'api')),
                );
                break;

            case 'heartbeat':
                $this->heartbeat($location, $request->input('last_reminder_at'));
                break;

            case 'update':
                $this->applySettings($location, $request);
                break;

            case 'set_manual_accept':
                $this->setManualAccept($location, (bool) $request->input('manual_accept'));
                break;

            default:
                return response()->json(['message' => "Unknown action: {$action}"], 422);
        }

        // Reads after a save() are stale (settings keys are purged from the model
        // attributes on save), so drop the internal cache and re-read from DB.
        LocationSettings::clearInternalCache();

        return response()->json($this->statePayload($location));
    }

    /**
     * Mark ordering paused. The PauseWorkingSchedule listener reads this flag and
     * forces the schedule closed; order types + location stay enabled. Idempotent:
     * a second pause is a no-op so paused_since / paused_by are not overwritten.
     */
    protected function pause(Location $location, string $source, ?string $reason): void
    {
        $state = LocationSettings::instance($location, $this->stateItem);
        if ($state->get('paused')) {
            return;
        }

        $state->paused = true;
        $state->paused_by = $source;
        $state->paused_since = Carbon::now()->toIso8601String();
        $state->reason = $reason;
        $state->save();
    }

    /**
     * Clear the pause flag; the schedule rebuilds without exceptions on the next
     * request. Idempotent. A resume from the printer will not clobber a manual
     * admin pause; a manual admin resume overrides regardless.
     */
    protected function resume(Location $location, string $source): void
    {
        $state = LocationSettings::instance($location, $this->stateItem);
        if (!$state->get('paused')) {
            return;
        }

        if ($source === 'printer' && $state->get('paused_by') !== 'printer') {
            return;
        }

        $state->paused = false;
        $state->paused_by = null;
        $state->paused_since = null;
        $state->last_reminder_at = null;
        $state->reason = null;
        $state->save();
    }

    /**
     * Toggle manual-accept mode for this restaurant. OFF (default) = auto: the
     * AutoAcceptOrder listener promotes new orders 1 -> 10 the instant they're
     * paid. ON = the Order Manager app is the acceptance gate; orders rest at 1
     * (the pool) until the owner accepts. Read by AutoAcceptOrder.
     */
    protected function setManualAccept(Location $location, bool $manualAccept): void
    {
        $state = LocationSettings::instance($location, $this->stateItem);
        $state->manual_accept = $manualAccept;
        $state->save();
    }

    /** Persist the reminder timestamp so the print-server's cadence survives a restart. */
    protected function heartbeat(Location $location, mixed $lastReminderAt): void
    {
        $state = LocationSettings::instance($location, $this->stateItem);
        $state->last_reminder_at = $lastReminderAt
            ? Carbon::parse($lastReminderAt)->toIso8601String()
            : Carbon::now()->toIso8601String();
        $state->save();
    }

    /**
     * Direct lead_time / time_interval / is_enabled writes ("give the kitchen a
     * break"). Shares the LocationSettings write path so sibling keys survive.
     */
    protected function applySettings(Location $location, Request $request): void
    {
        foreach ($this->orderTypes as $type) {
            $data = $request->input($type);
            if (!is_array($data)) {
                continue;
            }

            $settings = LocationSettings::instance($location, $type);
            foreach (['lead_time', 'time_interval', 'is_enabled'] as $key) {
                if (array_key_exists($key, $data)) {
                    $settings->{$key} = $data[$key];
                }
            }
            $settings->save();
        }
    }

    protected function statePayload(Location $location): array
    {
        $state = LocationSettings::instance($location, $this->stateItem);

        $orderTypes = [];
        foreach ($this->orderTypes as $type) {
            $settings = LocationSettings::instance($location, $type);
            $orderTypes[$type] = [
                'is_enabled' => (bool) $settings->get('is_enabled', 1),
                'lead_time' => (int) $settings->get('lead_time', 15),
                'time_interval' => (int) $settings->get('time_interval', 15),
            ];
        }

        return [
            'location_id' => $location->getKey(),
            'manual_accept' => (bool) $state->get('manual_accept', false),
            'paused' => (bool) $state->get('paused', false),
            'paused_by' => $state->get('paused_by'),
            'paused_since' => $state->get('paused_since'),
            'last_reminder_at' => $state->get('last_reminder_at'),
            'reason' => $state->get('reason'),
            'order_types' => $orderTypes,
        ];
    }

    protected function resolveLocation(Request $request): ?Location
    {
        if ($request->filled('location_id')) {
            return Location::query()->find($request->integer('location_id'));
        }

        return Location::getDefault() ?? Location::query()->first();
    }

    /** Map the transport-level source onto our paused_by vocabulary. */
    protected function normalizeSource(string $source): string
    {
        return in_array($source, ['printer', 'print-server'], true) ? 'printer' : $source;
    }
}
