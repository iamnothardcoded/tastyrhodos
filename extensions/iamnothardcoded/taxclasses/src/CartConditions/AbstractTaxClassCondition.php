<?php

declare(strict_types=1);

namespace Iamnothardcoded\TaxClasses\CartConditions;

use Iamnothardcoded\TaxClasses\Classes\DiscountAllocator;
use Iamnothardcoded\TaxClasses\Classes\TaxClassResolver;
use Iamnothardcoded\TaxClasses\Models\TaxClassSettings;
use Igniter\Cart\CartCondition;
use Igniter\Cart\CartContent;
use Igniter\Cart\CartItem;
use Igniter\Cart\Facades\Cart;
use Igniter\Local\Facades\Location;
use Igniter\System\Models\Currency;
use Override;

/**
 * One condition per tax class, mirroring the core Tax condition. Each computes
 * its own base (sum of matching items' gross subtotals, plus the delivery charge
 * for the configured class) and reports back-calculated inclusive VAT — the
 * running cart total is never modified (gross prices, PAngV).
 */
abstract class AbstractTaxClassCondition extends CartCondition
{
    /** Set by subclass: 'reduced' | 'standard' */
    protected string $taxClass = '';

    protected float $rate = 0;

    protected float $backRate = 0;

    protected bool $taxesDelivery = false;

    #[Override]
    public function onLoad(): void
    {
        $default = $this->taxClass === 'reduced' ? 7 : 19;
        $this->rate = (float)TaxClassSettings::get($this->taxClass.'_rate', $default);
        // Gross back-calculation, same math as core Tax (7% → 6.5421% of gross).
        $this->backRate = $this->rate > 0 ? $this->rate / ((100 + $this->rate) / 100) : 0;
        $this->taxesDelivery = TaxClassSettings::get('delivery_charge_class', 'standard') === $this->taxClass;
    }

    #[Override]
    public function getLabel(): string
    {
        $rate = rtrim(rtrim(number_format($this->rate, 2, '.', ''), '0'), '.');

        return sprintf(lang('iamnothardcoded.taxclasses::default.text_incl_vat'), $rate);
    }

    #[Override]
    public function beforeApply(): ?bool
    {
        // target is set before apply() (CartConditions::apply → withTarget). If core
        // ever reorders that, fail open — a zero base then just reports 0.
        if (!$this->target instanceof CartContent) {
            return true;
        }

        // false ⇒ condition never marked applied ⇒ no storefront line and no
        // order_totals row for a class with no items in the cart.
        return $this->rate > 0 && $this->classBase() > 0;
    }

    #[Override]
    public function getActions(): array
    {
        $precision = optional(Currency::getDefault())->decimal_position ?? 2;

        return [
            [
                'value' => sprintf('+%s%%', $this->backRate),
                'inclusive' => true,
                'valuePrecision' => (int)$precision,
            ],
        ];
    }

    #[Override]
    public function calculate($subTotal)
    {
        $bases = $this->classBases();
        $base = $bases[$this->taxClass] ?? 0.0;

        // Cart-level discounts applied before us (signup discount, whole-cart
        // coupon) reduce the Entgelt — shrink the per-class bases pro-rata
        // (§ 10 UStG; largest-remainder so the shares sum exactly to D).
        if (($discount = $this->cartLevelDiscount()) > 0) {
            $share = DiscountAllocator::allocate($discount, $bases)[$this->taxClass] ?? 0.0;
            $base = max(0.0, $base - $share);
        }

        if ($this->taxesDelivery && Location::orderTypeIsDelivery()) {
            $base += (float)Location::coveredArea()->deliveryAmount($subTotal);
        }

        // Loose coupling: other extensions contribute gross amounts taxed
        // inside a class without a hard dependency (e.g. Bottle Deposit —
        // deposits are a Nebenleistung taxed at the carrying drink's rate).
        // Each listener returns [class => gross amount]. Added AFTER the
        // discount allocation on purpose: these amounts are never part of the
        // discountable Entgelt (a deposit is a refundable security).
        if ($this->target instanceof CartContent) {
            foreach (\Illuminate\Support\Facades\Event::dispatch('iamnothardcoded.taxclasses.collectExtraBases', [$this->target]) as $extra) {
                if (is_array($extra)) {
                    $base += (float)($extra[$this->taxClass] ?? 0);
                }
            }
        }

        // Run the inclusive action against OUR base (sets calculatedValue = the
        // reported VAT, rounded once per class), then leave the cart total untouched.
        parent::calculate($base);

        return $subTotal;
    }

    protected function classBase(): float
    {
        return $this->classBases()[$this->taxClass] ?? 0.0;
    }

    /** @return array<string, float> class => gross item base (item-level
     *  conditions included, cart-level conditions not — see cartLevelDiscount) */
    protected function classBases(): array
    {
        /** @var CartContent $content */
        $content = $this->target;

        $classes = TaxClassResolver::forMenuIds(
            $content->map(fn(CartItem $item) => $item->id)->unique()->values()->all(),
        );

        $bases = array_fill_keys(TaxClassResolver::CLASSES, 0.0);
        $content->each(function(CartItem $item) use ($classes, &$bases): void {
            $class = $classes[(int)$item->id] ?? TaxClassResolver::defaultClass();
            $bases[$class] += (float)$item->subtotal;
        });

        return $bases;
    }

    /**
     * Total cart-level discount already applied in this pass. Conditions with a
     * lower priority ran before us and their calculatedValue is final — the
     * instances are shared and mutated in place during CartConditions::apply().
     * conditionsWithoutApplied() is the non-reentrant accessor (Cart::conditions()
     * would re-run the whole apply reduce from inside it). Discounts are the
     * conditions reporting a negative getValue(); additive conditions
     * (delivery/tip) report positive, unapplied ones 0. Item-scoped coupons
     * never apply at cart level and already live inside $item->subtotal, so
     * they are correctly not counted here.
     */
    protected function cartLevelDiscount(): float
    {
        $ownPriority = (int)($this->priority ?? 0);

        return (float)Cart::conditionsWithoutApplied()
            ->filter(function(CartCondition $condition) use ($ownPriority): bool {
                if ((int)($condition->getPriority() ?? 0) >= $ownPriority) {
                    return false;
                }

                // Only conditions APPLIED this pass count. A condition whose
                // beforeApply() returned false (e.g. a discount that dropped
                // below its min_total when an item was removed) keeps a stale
                // negative getValue() — isValid() (passed flag) gates it out.
                if (!$condition->isValid()) {
                    return false;
                }

                // Delivery-fee coupons discount the delivery charge, not the
                // items — don't shrink the item bases with them (the VAT on the
                // delivery charge itself stays un-reduced: documented edge).
                if (class_exists(\Igniter\Coupons\CartConditions\Coupon::class)
                    && $condition instanceof \Igniter\Coupons\CartConditions\Coupon
                    && $condition->getModel()?->appliesOnDelivery()) {
                    return false;
                }

                return (float)$condition->getValue() < 0;
            })
            ->sum(fn(CartCondition $condition): float => -(float)$condition->getValue());
    }
}
