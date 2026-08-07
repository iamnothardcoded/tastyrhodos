<?php

declare(strict_types=1);

namespace Jamasa\Core\Listeners;

use Igniter\Cart\Models\Order;
use Igniter\Local\Models\Location as LocationModel;
use Illuminate\Support\Facades\Mail;
use Jamasa\Core\Helpers\PickupCode;
use Throwable;

/**
 * Tell the RESTAURANT when an order is cancelled.
 *
 * Why this exists at all: since 2026-08-07 the location no longer receives the
 * per-order alert — the printer is the order channel and a mail copy of every
 * order is noise (and ESP quota, which is shared fleet-wide and gates login).
 * But a CANCELLATION is the one case the printed slip cannot carry: the ticket
 * is already in the kitchen and is now wrong. Nobody would otherwise learn of it
 * unless a staff member happens to be looking at the Order Manager.
 *
 * ⚠️ TI cannot express this on its own: status-change mail is hardcoded to the
 * CUSTOMER (`mailSend('…order_update', 'customer')` in ti-ext-cart), and
 * `mailSend(…, 'location')` is gated on `order_email` containing "location" —
 * which we deliberately removed. Hence a direct send.
 */
class NotifyLocationOnCancel
{
    public function handle(Order $order, $statusHistory): void
    {
        $cancelled = (int) setting('canceled_order_status', 9);
        if ((int) ($statusHistory->status_id ?? 0) !== $cancelled) {
            return;
        }

        $to = (string) (optional(LocationModel::query()->orderBy('location_id')->first())->location_email);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;                       // no real address known — stay silent
        }

        $code = PickupCode::fromHash($order->hash);
        $name = trim(($order->first_name ?? '').' '.($order->last_name ?? ''));

        // rescue(): a cancellation must never fail because the relay hiccuped —
        // the order is already cancelled in the system either way.
        rescue(function() use ($order, $to, $code, $name): void {
            Mail::send([
                'html' => 'jamasa::mail.order-cancelled',
                'text' => 'jamasa::mail.order-cancelled_text',
            ], [
                'code' => $code,
                'orderId' => $order->order_id,
                'customerName' => $name !== '' ? $name : 'Gast',
                'orderType' => $order->order_type === 'collection' ? 'Abholung' : 'Lieferung',
                'orderTotal' => currency_format($order->order_total),
                'siteName' => (string) setting('site_name'),
            ], function($message) use ($to, $code): void {
                $message->to($to)
                    ->subject('STORNIERT – Bestellung '.$code);
            });
        }, null, false);
    }
}
