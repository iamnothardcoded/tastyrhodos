<?php

declare(strict_types=1);

namespace Iamnothardcoded\BottleDeposit\CartConditions;

use Iamnothardcoded\BottleDeposit\Classes\DepositClasses;
use Iamnothardcoded\BottleDeposit\Classes\DepositResolver;
use Igniter\Cart\CartCondition;
use Igniter\Cart\CartContent;
use Igniter\Cart\CartItem;
use Override;

/**
 * One summable "deposit" line for the whole cart: Σ (item deposit × qty).
 *
 * PAngV §7: the deposit is never part of an item's displayed price — it is
 * charged on top, as its own clearly separated amount. Being a summable order
 * total, the line shows on every TastyIgniter surface (cart, checkout,
 * confirmation, invoice, mails) with no theme changes.
 *
 * Priority: run AFTER discount conditions (deposits are a refundable
 * security, never part of the discountable Entgelt) and BEFORE any tax
 * breakdown conditions. 108 slots between common discount priorities
 * (~104/105) and the Tax Classes extension's 110/115.
 */
class BottleDeposit extends CartCondition
{
    public ?int $priority = 108;

    protected float $depositTotal = 0.0;

    #[Override]
    public function getLabel(): string
    {
        return lang('iamnothardcoded.bottledeposit::default.text_deposit');
    }

    #[Override]
    public function beforeApply(): ?bool
    {
        $this->depositTotal = $this->target instanceof CartContent
            ? round($this->totalFor($this->target), 2)
            : 0.0;

        // false ⇒ no cart line and no order_totals row for deposit-free carts.
        return $this->depositTotal > 0;
    }

    #[Override]
    public function getActions(): array
    {
        return [
            ['value' => '+'.$this->depositTotal],
        ];
    }

    /**
     * Deposit per tax class of the carrying items — consumed by the Tax
     * Classes extension (deposits are a Nebenleistung: taxed at the drink's
     * rate, UStAE 10.1 Abs. 8). Keyed by tax class code; falls back to one
     * 'standard' bucket when Tax Classes is not installed (unused then).
     *
     * @return array<string, float> tax class => gross deposit amount
     */
    public function totalsByTaxClass(CartContent $content): array
    {
        $menuIds = $content->map(fn(CartItem $item) => $item->id)->unique()->values()->all();
        $depositClasses = DepositResolver::forMenuIds($menuIds);

        $taxClasses = class_exists(\Iamnothardcoded\TaxClasses\Classes\TaxClassResolver::class)
            ? \Iamnothardcoded\TaxClasses\Classes\TaxClassResolver::forMenuIds($menuIds)
            : [];

        $totals = [];
        $content->each(function(CartItem $item) use ($depositClasses, $taxClasses, &$totals): void {
            $amount = DepositClasses::amountFor($depositClasses[(int)$item->id] ?? null);
            if ($amount <= 0) {
                return;
            }

            $taxClass = $taxClasses[(int)$item->id] ?? 'standard';
            $totals[$taxClass] = ($totals[$taxClass] ?? 0.0) + $amount * (int)$item->qty;
        });

        return array_map(fn(float $total): float => round($total, 2), $totals);
    }

    protected function totalFor(CartContent $content): float
    {
        $classes = DepositResolver::forMenuIds(
            $content->map(fn(CartItem $item) => $item->id)->unique()->values()->all(),
        );

        $total = 0.0;
        $content->each(function(CartItem $item) use ($classes, &$total): void {
            $total += DepositClasses::amountFor($classes[(int)$item->id] ?? null) * (int)$item->qty;
        });

        return $total;
    }
}
