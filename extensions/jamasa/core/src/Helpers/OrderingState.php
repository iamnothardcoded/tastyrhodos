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
     * Lang key for the copy when the kitchen is paused because the printer is
     * offline. Override via config `jamasa.core.pause_message`.
     */
    public const DEFAULT_MESSAGE_KEY = 'jamasa.core::default.pause.printer_message';

    /**
     * Lang key for the copy when the kitchen manually paused because it is
     * overloaded. Override via config `jamasa.core.busy_message`.
     */
    public const BUSY_MESSAGE_KEY = 'jamasa.core::default.pause.busy_message';

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
