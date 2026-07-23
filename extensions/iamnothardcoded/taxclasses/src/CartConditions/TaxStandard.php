<?php

declare(strict_types=1);

namespace Iamnothardcoded\TaxClasses\CartConditions;

class TaxStandard extends AbstractTaxClassCondition
{
    // order_totals.priority is tinyint(1) — must stay < 127; delivery renders at 100.
    public ?int $priority = 115;

    protected string $taxClass = 'standard';
}
