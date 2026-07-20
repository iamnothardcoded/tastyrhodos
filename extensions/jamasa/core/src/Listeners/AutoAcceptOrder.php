<?php

declare(strict_types=1);

namespace Jamasa\Core\Listeners;

use Igniter\Cart\Models\Order;
use Igniter\Local\Models\LocationSettings;
use Throwable;

/**
 * Auto-accept in the famedo order rail.
 *
 * Fires on `admin.order.paymentProcessed` — the moment an order becomes real
 * (paid & entered; identical for cash and online). By then the order is already
 * at status 1 (the pool). This is the ONE place "acceptance" lives:
 *
 *  - AUTO mode (manual_accept off): promote 1 -> 10 immediately, so the printer
 *    (which watches status 10) prints it. This is the invisible default.
 *  - MANUAL mode (manual_accept on): do nothing — the order stays at 1 and waits
 *    for the owner to accept it in the Order Manager app.
 *
 * ⚠️ This runs INSIDE the payment request. It must never throw: the order and the
 * payment are already committed by the time we run, so a crash cannot lose them,
 * but an unhandled exception could spoil the rest of that request. Everything is
 * wrapped; failures are reported and swallowed. The print-server's mode-blind
 * failsafe sweep is the backstop if this ever no-ops (it promotes stale status-1
 * orders), so a swallowed error here still self-heals.
 */
class AutoAcceptOrder
{
    /** The "Angenommen" print-queue status (see the jamasa migration). */
    public const ACCEPTED_STATUS = 10;

    public function handle(Order $order): void
    {
        try {
            $location = $order->location;
            if (!$location) {
                return;
            }

            $manual = (bool) LocationSettings::instance($location, 'jamasa_ordering_state')
                ->get('manual_accept', false);

            if ($manual) {
                return; // manual mode: leave it in the pool (status 1)
            }

            // auto mode: accept now so the printer picks it up. notify=false —
            // the customer's "In Zubereitung" mail fires when the printer moves
            // it 10 -> 3, not here.
            $order->updateOrderStatus(self::ACCEPTED_STATUS, ['notify' => false]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
