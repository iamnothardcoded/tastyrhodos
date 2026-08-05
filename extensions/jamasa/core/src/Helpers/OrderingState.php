<?php

declare(strict_types=1);

namespace Jamasa\Core\Helpers;

use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Local\Models\LocationSettings;
use Throwable;

/**
 * Read-side helper for the jamasa_ordering_state settings row that
 * OrderingSettingsController writes.
 *
 * Two consumers:
 *  - PauseWorkingSchedule listener: reads isPaused() to force the schedule closed.
 *  - fulfillment.blade override: reads isPaused()/message() to swap the bare
 *    "CLOSED" for friendly, reason-specific copy while paused.
 */
final class OrderingState
{
    /** Row that holds the pause state (written by OrderingSettingsController). */
    private const STATE_ITEM = 'jamasa_ordering_state';

    /**
     * Lang key for the copy when the kitchen is paused because the printer is
     * offline. Override via config `jamasa.core.pause_message`.
     */
    public const DEFAULT_MESSAGE_KEY = 'jamasa.core::default.pause.printer_message';

    /**
     * Lang key for the copy when the kitchen manually paused because it is
     * overloaded. Override via config `jamasa.core.busy_message`.
     */
    public const BUSY_MESSAGE_KEY = 'jamasa.core::default.pause.busy_message';

    /**
     * Re-entrancy guard for the schedule-aware window check below: while we build
     * a RAW schedule (to read the true opening hours), PauseWorkingSchedule must
     * NOT inject pause exceptions — the pause would read its own output and latch
     * closed forever. The listener checks this flag first.
     */
    public static bool $buildingRawSchedule = false;

    /** Per-request memo for the window check (schedule build is not free). */
    private static array $windowMemo = [];

    /**
     * True when ordering is EFFECTIVELY paused.
     *
     * Manual pauses (overloaded / api / operator) are absolute. A PRINTER-caused
     * pause is schedule-aware (v2, 2026-08-05): it only takes effect inside the
     * operationally relevant window [today's opening − prep window, close] read
     * from the RAW opening hours. Outside it (overnight), a sleeping printer is
     * normal life — and blocking same-day preorders for it would be wrong; the
     * parked slips print when the printer wakes (SDP pull) or at release time.
     * Evaluated per request, so the pause engages by itself at opening − prep
     * even though a long-dead printer produces no new monitor events.
     *
     * The raw flag written by the monitor is untouched (see isPausedRaw) — the
     * print-server's reconcile loop keeps seeing its own state truthfully.
     */
    public static function isPaused(?LocationModel $location = null): bool
    {
        $state = self::state($location);
        if (!(bool) $state?->get('paused')) {
            return false;
        }

        if ((string) $state->get('paused_by') === 'printer' && !self::inPrinterWindow($location)) {
            return false;
        }

        return true;
    }

    /** The plain stored flag, no schedule awareness — for the monitor API. */
    public static function isPausedRaw(?LocationModel $location = null): bool
    {
        return (bool) self::state($location)?->get('paused');
    }

    private static function inPrinterWindow(?LocationModel $location): bool
    {
        $key = ($location?->getKey() ?? 0).':'.now()->format('YmdHi');

        return self::$windowMemo[$key] ??= self::computeInPrinterWindow($location);
    }

    private static function computeInPrinterWindow(?LocationModel $location): bool
    {
        try {
            $location ??= Location::current();
            if (!$location) {
                return true; // unknown → fail safe: the pause stands
            }

            $prep = (int) config('jamasa.core.preorder_release_minutes', 60);

            // Build the RAW schedule (guarded — see $buildingRawSchedule).
            self::$buildingRawSchedule = true;
            try {
                $schedule = $location->newWorkingSchedule(LocationModel::OPENING);
            } finally {
                self::$buildingRawSchedule = false;
            }

            // Yesterday first: a range crossing midnight (close < open) belongs
            // to yesterday's row but may still cover "now".
            foreach ([now()->subDay(), now()] as $day) {
                foreach ($schedule->forDate($day) as $range) {
                    $open = $day->copy()->setTimeFromTimeString((string) $range->start());
                    $close = $day->copy()->setTimeFromTimeString((string) $range->end());
                    if ($range->endsNextDay()) {
                        $close = $close->addDay();
                    }
                    if (now()->between($open->copy()->subMinutes($prep), $close)) {
                        return true;
                    }
                }
            }

            return false;
        } catch (Throwable) {
            return true; // any doubt → the pause stands (fail safe)
        }
    }

    /** True only when the pause was triggered by the printer going offline. */
    public static function isPrinterPaused(?LocationModel $location = null): bool
    {
        return self::isPaused($location) && self::pausedBy($location) === 'printer';
    }

    /** Who/what triggered the pause: 'printer', 'overloaded', 'api', an operator, ... */
    public static function pausedBy(?LocationModel $location = null): ?string
    {
        $by = self::state($location)?->get('paused_by');

        return $by === null ? null : (string) $by;
    }

    /** Customer-facing message for the current pause reason. */
    public static function message(?LocationModel $location = null): string
    {
        // An explicit message stored on the state row wins (future operator UI).
        if ($custom = self::state($location)?->get('message')) {
            return (string) $custom;
        }

        if (self::pausedBy($location) === 'overloaded') {
            return (string) config('jamasa.core.busy_message', lang(self::BUSY_MESSAGE_KEY));
        }

        return (string) config('jamasa.core.pause_message', lang(self::DEFAULT_MESSAGE_KEY));
    }

    private static function state(?LocationModel $location): ?LocationSettings
    {
        $location ??= Location::current();
        if (!$location) {
            return null;
        }

        return LocationSettings::instance($location, self::STATE_ITEM);
    }
}
