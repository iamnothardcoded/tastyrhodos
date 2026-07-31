<?php

declare(strict_types=1);

namespace Iamnothardcoded\BottleDeposit\Listeners;

use Iamnothardcoded\BottleDeposit\CartConditions\BottleDeposit;
use Igniter\Cart\CartContent;
use Igniter\Cart\Facades\Cart;

/**
 * Feeds the charged deposits into the Tax Classes extension's per-class VAT
 * bases (event `iamnothardcoded.taxclasses.collectExtraBases`). Deposits on
 * bottles are a Nebenleistung to the drink (UStAE 10.1 Abs. 8) — their VAT
 * belongs in the drink's class. Registered only when Tax Classes fires the
 * event; without it this listener is inert.
 */
class ContributesTaxBase
{
    /** @return array<string, float> tax class => gross deposit amount */
    public function __invoke(CartContent $content): array
    {
        // Only contribute what is actually being charged: if the deposit
        // condition is disabled (or didn't apply), there is no deposit line
        // and no extra VAT base.
        $condition = Cart::conditionsWithoutApplied()
            ->first(fn($c): bool => $c instanceof BottleDeposit && $c->isValid());

        if (!$condition instanceof BottleDeposit) {
            return [];
        }

        return $condition->totalsByTaxClass($content);
    }
}
