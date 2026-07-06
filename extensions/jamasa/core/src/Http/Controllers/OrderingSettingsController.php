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
 * Pauses/resumes online ordering by flipping the order-type `is_enabled` flags
 * in `location_settings` — the same rows the storefront reads via
 * Location::getSettings('collection.is_enabled'). This never touches
 * `location_status`, so it stays clear of the disabled-location path and shows
 * TastyIgniter's existing "CLOSED" state instead of a hard-closed location.
 *
 * State + a snapshot of the pre-pause flags live in a dedicated
 * `jamasa_ordering_state` settings row so resume restores the exact prior state
 * (not a hardcoded default) and pause/resume are idempotent.
 */
class OrderingSettingsController extends Controller
{
    /** Order types we gate. */
    protected array $orderTypes = ['collection', 'delivery'];

    /** The settings row that holds our pause state + snapshot. */
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

            default:
                return response()->json(['message' => "Unknown action: {$action}"], 422);
        }

        // Reads after a save() are stale (settings keys are purged from the model
        // attributes on save), so drop the internal cache and re-read from DB.
        LocationSettings::clearInternalCache();

        return response()->json($this->statePayload($location));
    }

    /**
     * Disable every currently-enabled order type, remembering the prior state.
     * Idempotent: if already paused we do NOT re-snapshot (that would capture the
     * already-disabled values and make resume a no-op).
     */
    protected function pause(Location $location, string $source, ?string $reason): void
    {
        $state = LocationSettings::instance($location, $this->stateItem);
        if ($state->get('paused')) {
            return;
        }

        $snapshot = [];
        foreach ($this->orderTypes as $type) {
            $settings = LocationSettings::instance($location, $type);
            $enabled = (int) $settings->get('is_enabled', 1);
            $snapshot[$type] = ['is_enabled' => $enabled];

            if ($enabled === 1) {
                $settings->is_enabled = 0;
                $settings->save();
            }
        }

        $state->paused = true;
        $state->paused_by = $source;
        $state->paused_since = Carbon::now()->toIso8601String();
        $state->reason = $reason;
        $state->snapshot = $snapshot;
        $state->save();
    }

    /**
     * Restore each order type to its snapshotted state. Idempotent. A resume from
     * the printer will not clobber a manual admin pause; a manual admin resume
     * overrides regardless.
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

        $snapshot = (array) $state->get('snapshot', []);
        foreach ($this->orderTypes as $type) {
            $previous = (int) ($snapshot[$type]['is_enabled'] ?? 1);
            $settings = LocationSettings::instance($location, $type);
            $settings->is_enabled = $previous;
            $settings->save();
        }

        $state->paused = false;
        $state->paused_by = null;
        $state->paused_since = null;
        $state->last_reminder_at = null;
        $state->reason = null;
        $state->snapshot = [];
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
