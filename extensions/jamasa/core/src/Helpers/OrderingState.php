<?php

declare(strict_types=1);

namespace Jamasa\Core\Helpers;

use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Local\Models\LocationSettings;

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
     * Default copy when the kitchen is paused because the printer is offline.
     * Override via config `jamasa.core.pause_message`.
     */
    public const DEFAULT_MESSAGE = 'Die Küche macht gerade eine kurze Pause – gleich wieder für dich da!';

    /**
     * Copy when the kitchen manually paused because it is overloaded.
     * Override via config `jamasa.core.busy_message`.
     */
    public const BUSY_MESSAGE = 'Wir haben gerade sehr viel zu tun – bitte versuche es in Kürze noch einmal!';

    /** True when ordering is paused for ANY reason (printer, manual, overloaded). */
    public static function isPaused(?LocationModel $location = null): bool
    {
        return (bool) self::state($location)?->get('paused');
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
            return (string) config('jamasa.core.busy_message', self::BUSY_MESSAGE);
        }

        return (string) config('jamasa.core.pause_message', self::DEFAULT_MESSAGE);
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
