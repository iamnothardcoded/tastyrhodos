<?php

declare(strict_types=1);

namespace Jamasa\Core\Helpers;

use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Local\Models\LocationSettings;

/**
 * Read-side helper for the jamasa_ordering_state settings row that
 * OrderingSettingsController writes. Used by view overrides to show friendly
 * "kitchen on a break" copy when ordering was paused by the printer going
 * offline (rather than the stock "CLOSED").
 */
final class OrderingState
{
    /**
     * Default customer-facing message when ordering is paused because the
     * kitchen printer is offline. Edit here (or set config `jamasa.core.pause_message`).
     */
    public const DEFAULT_MESSAGE = 'Die Küche macht gerade eine kurze Pause – gleich wieder für dich da!';

    public static function isPrinterPaused(?LocationModel $location = null): bool
    {
        $location ??= Location::current();
        if (!$location) {
            return false;
        }

        $state = LocationSettings::instance($location, 'jamasa_ordering_state');

        return (bool) $state->get('paused') && $state->get('paused_by') === 'printer';
    }

    public static function message(): string
    {
        return (string) config('jamasa.core.pause_message', self::DEFAULT_MESSAGE);
    }
}
