<?php

declare(strict_types=1);

namespace Iamnothardcoded\SignupDiscounts\CartConditions;

use Iamnothardcoded\SignupDiscounts\Classes\DiscountManager;
use Igniter\Cart\CartCondition;
use Igniter\Cart\CartContent;
use Igniter\User\Facades\Auth;
use Override;

/**
 * Automatic discount for logged-in customers, driven by the two discount slots
 * in the extension settings. Emits a fixed euro amount computed from the ITEMS
 * subtotal — never a % action, which would hit the running total that already
 * contains delivery/tip (priority 100). Runs after those, and before any
 * tax conditions that want to see the discounted total.
 */
class SignupDiscount extends CartCondition
{
    // order_totals.priority is tinyint(1) — must stay < 127.
    public ?int $priority = 104;

    protected ?array $campaign = null;

    protected float $discountAmount = 0;

    #[Override]
    public function beforeApply(): ?bool
    {
        $this->campaign = null;
        $this->discountAmount = 0;

        if (!$this->target instanceof CartContent) {
            return false;
        }

        if (!$customer = Auth::customer()) {
            return false;
        }

        if (!$campaign = DiscountManager::bestDiscount($customer, $this->target)) {
            return false;
        }

        $this->campaign = $campaign;
        $this->discountAmount = (float)$campaign['amount'];

        return $this->discountAmount > 0;
    }

    #[Override]
    public function getLabel(): string
    {
        $label = trim((string)($this->campaign['label'] ?? ''));

        return $label !== '' ? $label : lang('iamnothardcoded.signupdiscounts::default.text_signup_discount');
    }

    /** Negative ⇒ a summable discount row in order_totals (mirrors the native
     *  coupon condition) — also what discount-aware tax conditions key on. */
    #[Override]
    public function getValue(): int|float
    {
        return 0 - $this->calculatedValue;
    }

    #[Override]
    public function getActions(): array
    {
        if ($this->discountAmount <= 0) {
            return [];
        }

        return [
            ['value' => '-'.number_format($this->discountAmount, 2, '.', '')],
        ];
    }
}
