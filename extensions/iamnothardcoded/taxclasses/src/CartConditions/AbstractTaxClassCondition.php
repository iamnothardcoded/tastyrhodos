<?php

declare(strict_types=1);

namespace Iamnothardcoded\TaxClasses\CartConditions;

use Iamnothardcoded\TaxClasses\Classes\TaxClassResolver;
use Iamnothardcoded\TaxClasses\Models\TaxClassSettings;
use Igniter\Cart\CartCondition;
use Igniter\Cart\CartContent;
use Igniter\Cart\CartItem;
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
        $base = $this->classBase();

        if ($this->taxesDelivery && Location::orderTypeIsDelivery()) {
            $base += (float)Location::coveredArea()->deliveryAmount($subTotal);
        }

        // Run the inclusive action against OUR base (sets calculatedValue = the
        // reported VAT, rounded once per class), then leave the cart total untouched.
        parent::calculate($base);

        return $subTotal;
    }

    protected function classBase(): float
    {
        /** @var CartContent $content */
        $content = $this->target;

        $classes = TaxClassResolver::forMenuIds(
            $content->map(fn(CartItem $item) => $item->id)->unique()->values()->all(),
        );

        return (float)$content->sum(fn(CartItem $item): float => ($classes[(int)$item->id] ?? TaxClassResolver::defaultClass()) === $this->taxClass
                ? (float)$item->subtotal : 0.0);
    }
}
