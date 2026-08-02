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
 * persisted, in TWO phases. ⚠️ isValid() alone is NOT enough: apply()
 * returns early when beforeApply() === false WITHOUT resetting the `passed`
 * flag or calculatedValue — both stay stale. So phase 1 re-asks
 * beforeApply() per condition (side effects included: good conditions reset
 * their calculated state in there), phase 2 runs one full apply pass so
 * every surviving condition's value is freshly computed, and only then are
 * the rows read. Reading values right after the per-condition re-ask
 * persisted zeroed discounts (2026-08-01 round 2). Everything else is
 * upstream verbatim.
 *
 * SECOND VARIANT of the same bug class (found 2026-08-02, elgrecomarl order
 * 15 — first real El Greco order): the filter above keeps stale conditions
 * out of the NEW totals array, but addOrderTotals() upserts per code and
 * NEVER DELETES, so a row persisted by an EARLIER pass survives every later
 * pass that excludes it. The earlier pass is mundane: merely RENDERING the
 * checkout page calls loadOrder(), which on first hit creates the order row
 * AND writes totals under the session state of that moment — and the session
 * order type DEFAULTS to delivery for a visitor who never touched the pill
 * (Location::orderType() falls back to LocationModel::DELIVERY). Visit
 * checkout → delivery row written → switch to Abholen in the fulfillment
 * modal → place order → the collection-pass upsert orphans the delivery row,
 * and calculateTotals() re-sums it into order_total. Dev repro order 293: a
 * COLLECTION order with order_total 7,50 = 5,00 subtotal + 2,50 stale
 * delivery fee. Elgrecomarl 15 showed 0,00 only because no delivery zone/fee
 * exists there yet. Fix: after every full-set totals write (saveOrder),
 * delete rows whose code the current pass did not produce, then re-sum.
 *
 * Registered as the OrderManager singleton rebind in Extension boot (same
 * pattern as the PayPalClient/Mollie workarounds). Remove when fixed
 * upstream (PR to tastyigniter/ti-ext-cart — see CLAUDE.md TODO).
 */
class FixedOrderManager extends OrderManager
{
    /**
     * Codes of the totals rows produced by the most recent getCartTotals()
     * pass — the authoritative row set for the prune in saveOrder().
     */
    private array $lastTotalsCodes = [];

    public function saveOrder($order, array $data)
    {
        $order = parent::saveOrder($order, $data);

        // parent::saveOrder() ran getCartTotals() (filling lastTotalsCodes)
        // and upserted those rows. Now drop any row an earlier pass left
        // behind that this pass did not produce (e.g. the `delivery` row from
        // rendering checkout while the session still said delivery), and
        // re-sum order_total without it. Empty-guard: whereNotIn with an
        // empty list would match — and delete — every row.
        if ($this->lastTotalsCodes !== []
            && $order->totals()->whereNotIn('code', $this->lastTotalsCodes)->delete() > 0) {
            $order->calculateTotals();
        }

        return $order;
    }

    public function getCartTotals()
    {
        // ⚠️ ORDER MATTERS. conditionApplies() calls beforeApply(), and
        // well-behaved conditions RESET their calculated state in there (the
        // signup discount zeroes calculatedValue defensively — persisting
        // right after the filter wrote a 0,00 discount row, found 2026-08-01
        // round 2). So: (1) decide applicability first (side effects and
        // all), (2) THEN run the full apply chain so every surviving
        // condition's calculatedValue is freshly computed under the current
        // order type, (3) only then read the values.
        $cartApplies = [];
        foreach ($this->cart->conditions() as $condition) {
            $cartApplies[$condition->name] = $this->conditionApplies($condition);
        }

        $itemApplies = [];
        foreach ($this->cart->content() as $cartItem) {
            foreach ($cartItem->conditions ?? [] as $condition) {
                $itemApplies[$cartItem->rowId][$condition->name] = $this->conditionApplies($condition);
            }
        }

        // Full fresh apply: recomputes calculatedValue for everything that
        // applies now; conditions skipped by their own beforeApply keep stale
        // values, but those are exactly the ones the applies-maps exclude.
        $this->cart->total();

        $itemConditions = [];
        foreach ($this->cart->content() as $cartItem) {
            foreach ($cartItem->conditions ?? [] as $condition) {
                if (!($itemApplies[$cartItem->rowId][$condition->name] ?? false)) {
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
            ->filter(fn(CartCondition $condition): bool => $cartApplies[$condition->name] ?? false)
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

        $this->lastTotalsCodes = array_column($totals, 'code');

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
