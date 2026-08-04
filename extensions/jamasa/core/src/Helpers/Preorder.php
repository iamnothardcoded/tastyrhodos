<?php

declare(strict_types=1);

namespace Jamasa\Core\Helpers;

use Igniter\Local\Facades\Location;
use Throwable;

/**
 * Same-day preorder: "closed right now, but you can already order for later
 * today" (tenant request 2026-08-05).
 *
 * Core does the heavy lifting: with `{type}.future_orders.is_enabled` on,
 * Location::checkOrderTime() bypasses the closed-now gate and validates the
 * chosen slot via isOpenAt(). `days` must be >= 1 to arm that bypass, which
 * technically opens tomorrow's window too — the timeslotValid listener in
 * Extension::boot() filters slots back down to TODAY (platform semantic:
 * strictly same-day), and the validateCheckout guard enforces it server-side.
 *
 * This helper answers the PRESENTATION question: is the current moment
 * "preorderable" (schedule closed, but valid same-day slots remain)? Consumers:
 * the famedo layout (third ordering state `preorder` — no lockout), the
 * fulfillment banner, and OrderingStatusController (30s storefront poll).
 *
 * Pause wins for free on both layers: the layout checks isPaused() FIRST, and
 * PauseWorkingSchedule closes TODAY via schedule exceptions — so while paused
 * there are no same-day slots left and this returns false server-side too.
 */
final class Preorder
{
    public static function isAvailable(): bool
    {
        try {
            $orderType = Location::getOrderType();
            if (!$orderType || $orderType->isDisabled()) {
                return false;
            }

            // Open now → normal ordering, preorder state doesn't apply.
            if ($orderType->getSchedule()->isOpen()) {
                return false;
            }

            if (!Location::current()?->hasFutureOrder($orderType->getCode())) {
                return false;
            }

            // Any valid slot left after the same-day filter? (scheduleTimeslot
            // is per-request cached; while paused today is exception-closed →
            // empty → false.)
            return Location::scheduleTimeslot($orderType->getCode())
                ->collapse()
                ->isNotEmpty();
        } catch (Throwable) {
            // Fail closed to the plain "closed" presentation — server-side
            // validation stays authoritative either way.
            return false;
        }
    }
}
