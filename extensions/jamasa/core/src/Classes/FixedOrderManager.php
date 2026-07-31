<?php

declare(strict_types=1);

namespace Jamasa\Core\Classes;

use Igniter\Cart\CartCondition;
use Igniter\Cart\Classes\OrderManager;

/**
 * Fixes the stale-condition order-totals bug (found 2026-08-01, live money):
 * upstream getCartTotals() maps EVERY loaded cart condition and writes its
 * getValue() with no applied/valid check. A condition whose beforeApply()
 * returned false in the current pass keeps the calculatedValue from an
 * earlier pass — so ordering with Liefern selected, switching to Abholen and
 * placing the order persists a summable stale `delivery` row, and
 * addOrderTotals()->calculateTotals() re-sums the rows into order_total: the
 * PICKUP customer is silently charged the delivery fee (display hides the
 * row — famedo's order items view skips `delivery` on collection orders).
 *
 * Fix: only conditions that apply under the CURRENT cart state are
 * persisted. ⚠️ isValid() alone is NOT enough: CartCondition::apply()
 * returns early when beforeApply() === false WITHOUT resetting the `passed`
 * flag or calculatedValue — both stay stale. So the filter re-asks
 * beforeApply() (cheap, idempotent for all our conditions) AND requires
 * isValid() (the validate() outcome for conditions that did run). Same
 * filter for item-level conditions. Everything else is upstream verbatim.
 *
 * Registered as the OrderManager singleton rebind in Extension boot (same
 * pattern as the PayPalClient/Mollie workarounds). Remove when fixed
 * upstream (PR to tastyigniter/ti-ext-cart — see CLAUDE.md TODO).
 */
class FixedOrderManager extends OrderManager
{
    public function getCartTotals()
    {
        // Refresh the apply chain FIRST: isValid() and calculatedValue are
        // whatever the last apply pass left (they serialize with the session
        // cart) — total() runs the reduce under the CURRENT order type, so
        // the filter below never judges on a previous request's state.
        $this->cart->total();

        $itemConditions = [];
        foreach ($this->cart->content() as $cartItem) {
            foreach ($cartItem->conditions ?? [] as $condition) {
                if (!$this->conditionApplies($condition)) {
                    continue;
                }

                $total = [
                    'code' => $condition->name,
                    'title' => $condition->getLabel(),
                    'value' => is_numeric($value = $condition->getValue()) ? $value : 0,
                    'priority' => $condition->getPriority() ?: 1,
                    'is_summable' => false,
                ];

                if (array_key_exists($condition->name, $itemConditions)) {
                    $itemConditions[$condition->name]['value'] += $total['value'];
                    continue;
                }

                $itemConditions[$condition->name] = $total;
            }
        }

        $totals = $this->cart->conditions()
            ->filter(fn(CartCondition $condition): bool => $this->conditionApplies($condition))
            ->map(fn(CartCondition $condition): array => [
                'code' => $condition->name,
                'title' => $condition->getLabel(),
                'value' => is_numeric($value = $condition->getValue()) ? $value : 0,
                'priority' => $condition->getPriority() ?: 1,
                'is_summable' => !$condition->isInclusive(),
            ])->merge($itemConditions)->all();

        $totals['subtotal'] = [
            'code' => 'subtotal',
            'title' => lang('igniter.cart::default.text_sub_total'),
            'value' => $this->cart->subtotal(),
            'priority' => 0,
            'is_summable' => false,
        ];

        $totals['total'] = [
            'code' => 'total',
            'title' => lang('igniter.cart::default.text_order_total'),
            'value' => max(0, $this->cart->total()),
            'priority' => 999,
            'is_summable' => false,
        ];

        return $totals;
    }

    /**
     * Does this condition apply to the cart AS IT IS NOW? beforeApply() is
     * the current-state gate (order type, amounts) — re-asked because a
     * skipped apply() leaves `passed`/calculatedValue stale; isValid() then
     * reflects validate() for conditions that did run. A beforeApply() that
     * throws (defensive — e.g. a coupon whose model vanished mid-session)
     * counts as "does not apply" rather than failing the order.
     */
    protected function conditionApplies(CartCondition $condition): bool
    {
        try {
            if ($condition->beforeApply() === false) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return (bool)$condition->isValid();
    }
}
