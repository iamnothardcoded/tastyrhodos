<?php

declare(strict_types=1);

namespace Jamasa\Core\Payments;

use Exception;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\PayRegister\Models\PaymentLog;
use Igniter\PayRegister\Payments\Mollie as BaseMollie;
use Illuminate\Support\Facades\Redirect;

/**
 * Fixed Mollie gateway. Upstream processReturnUrl has two bugs that send every
 * successfully paying customer back to the checkout page instead of the
 * success page (money still arrives — the notify webhook confirms the order
 * server-side — but the customer never sees the confirmation):
 *  1. inverted guard: `throw_unless(!$paymentId, ...)` throws exactly when the
 *     payment attempt IS found;
 *  2. "payment already processed" is treated as an error, but the webhook
 *     normally wins the race against the customer redirect, so that state is
 *     the SUCCESS case.
 * Wired via payments.class_name + registerPaymentGateways override in
 * Extension.php. Remove once fixed upstream (ti-ext-payregister).
 */
class Mollie extends BaseMollie
{
    public function processReturnUrl($params)
    {
        $hash = $params[0] ?? null;
        $redirectPage = input('redirect') ?: 'checkout.checkout';
        $cancelPage = input('cancel') ?: 'checkout.checkout';

        $order = $this->createOrderModel()->whereHash($hash)->first();

        try {
            throw_unless($order, new ApplicationException('No order found'));

            throw_if(
                !($paymentMethod = $order->payment_method) || !$paymentMethod->getGatewayObject() instanceof BaseMollie,
                new ApplicationException('No valid payment method found'),
            );

            // Webhook-first is the normal race outcome — success, not an error.
            if (!$order->isPaymentProcessed()) {
                $paymentId = array_get(PaymentLog::where('order_id', $order->order_id)
                    ->where('payment_code', $paymentMethod->code)
                    ->where('message', 'redirecting-to-payment-gateway')
                    ->value('response') ?? [], 'id');

                // upstream: throw_unless(!$paymentId, ...) — inverted
                throw_unless(
                    $paymentId,
                    new ApplicationException('Missing payment id in payment attempt records'),
                );

                throw_unless(
                    $payment = $this->createClient()->payments->get($paymentId),
                    new ApplicationException(sprintf('Payment not found for %s', $paymentId)),
                );

                if ($payment->isPaid() && data_get($payment->metadata, 'order_id') == $order->order_id) {
                    $order->logPaymentAttempt('Payment successful', 1, [], [
                        'id' => $payment->id,
                        'status' => $payment->status,
                        'method' => $payment->method,
                        'amount' => $payment->amount,
                    ], true);
                    $order->updateOrderStatus($paymentMethod->order_status, ['notify' => false]);
                    $order->markAsPaymentProcessed();
                }
            }

            return Redirect::to(page_url($redirectPage, [
                'id' => $order->getKey(),
                'hash' => $order->hash,
            ]));
        } catch (Exception $ex) {
            $order?->logPaymentAttempt('Payment error -> '.$ex->getMessage(), 0, [], request()->input());
            flash()->warning($ex->getMessage())->important();
        }

        return Redirect::to(page_url($cancelPage));
    }
}
