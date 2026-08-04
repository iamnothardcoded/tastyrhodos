<?php

declare(strict_types=1);

namespace Jamasa\Core\Http\Controllers;

use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Illuminate\Http\JsonResponse;
use Jamasa\Core\Helpers\OrderingState;
use Throwable;

/**
 * PUBLIC storefront polling endpoint for the closed/paused acknowledge-overlay:
 * the page polls this while ordering is unavailable and reloads itself when the
 * state changes (auto-resume after a pause, closed→paused flips).
 *
 * Deliberately anonymous (the overlay shows before any login) and minimal —
 * only {state, message}; no paused_by/paused_since/operator data. Any error
 * FAILS OPEN as 'open': a wrongly-cleared overlay self-corrects on the next
 * server-rendered page, a stuck overlay would strand the customer.
 */
class OrderingStatusController
{
    public function __invoke(): JsonResponse
    {
        try {
            $location = Location::current();
            if (!$location && ($location = LocationModel::getDefault())) {
                Location::setCurrent($location);
            }
            if (!$location) {
                return $this->open();
            }

            if (OrderingState::isPaused($location)) {
                return response()->json([
                    'state' => 'paused',
                    'message' => OrderingState::message($location),
                ]);
            }

            // Same expression the layout/fulfillment banner use. isDisabled()
            // covers only the admin toggle — after-hours needs the schedule check.
            // Closed splits into preorder (same-day slots still bookable — no
            // lockout client-side) vs plain closed.
            $orderType = Location::getOrderType();
            if (!$orderType || $orderType->isDisabled() || !$orderType->getSchedule()->isOpen()) {
                if (\Jamasa\Core\Helpers\Preorder::isAvailable()) {
                    return response()->json(['state' => 'preorder', 'message' => null]);
                }

                return response()->json(['state' => 'closed', 'message' => null]);
            }

            return $this->open();
        } catch (Throwable) {
            return $this->open();
        }
    }

    private function open(): JsonResponse
    {
        return response()->json(['state' => 'open', 'message' => null]);
    }
}
