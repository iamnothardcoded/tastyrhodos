<?php

declare(strict_types=1);

namespace Iamnothardcoded\TaxClasses\Tests;

use Iamnothardcoded\TaxClasses\Classes\DiscountAllocator;
use PHPUnit\Framework\TestCase;

/**
 * Pure-function guard for the § 10 UStG pro-rata VAT split. The invariants below
 * are the money-correctness contract that the discount-aware tax lines rest on.
 */
final class DiscountAllocatorTest extends TestCase
{
    public function test_shares_sum_to_exactly_the_discount(): void
    {
        // 3.21 across a 21.40/10.70 mixed cart — cent-exact, no drift.
        $shares = DiscountAllocator::allocate(3.21, ['reduced' => 21.40, 'standard' => 10.70]);

        $this->assertEqualsWithDelta(3.21, array_sum($shares), 0.00001);
        $this->assertEqualsWithDelta(2.14, $shares['reduced'], 0.005);  // 3.21 * 21.40/32.10
        $this->assertEqualsWithDelta(1.07, $shares['standard'], 0.005); // 3.21 * 10.70/32.10
    }

    public function test_no_share_exceeds_its_base(): void
    {
        $shares = DiscountAllocator::allocate(100.0, ['reduced' => 2.0, 'standard' => 5.0]);

        $this->assertLessThanOrEqual(2.0, $shares['reduced']);
        $this->assertLessThanOrEqual(5.0, $shares['standard']);
    }

    public function test_discount_larger_than_total_is_clamped_to_total(): void
    {
        $shares = DiscountAllocator::allocate(100.0, ['reduced' => 3.0, 'standard' => 4.0]);

        $this->assertEqualsWithDelta(7.0, array_sum($shares), 0.00001);
    }

    public function test_zero_and_empty_inputs_return_zeros(): void
    {
        $this->assertSame(0.0, array_sum(DiscountAllocator::allocate(0.0, ['reduced' => 10.0])));
        $this->assertSame([], DiscountAllocator::allocate(5.0, []));
        $this->assertSame(0.0, array_sum(DiscountAllocator::allocate(5.0, ['reduced' => 0.0, 'standard' => 0.0])));
    }

    public function test_single_class_cart_gets_the_whole_discount(): void
    {
        $shares = DiscountAllocator::allocate(2.50, ['reduced' => 20.0, 'standard' => 0.0]);

        $this->assertEqualsWithDelta(2.50, $shares['reduced'], 0.00001);
        $this->assertSame(0.0, $shares['standard']); // a zero base never receives a stray cent
    }

    public function test_largest_remainder_places_the_odd_cent_deterministically(): void
    {
        // 0.01 across two equal bases: exactly one cent, to exactly one class,
        // and the shares still sum to the discount (no lost/duplicated cent).
        $shares = DiscountAllocator::allocate(0.01, ['reduced' => 10.0, 'standard' => 10.0]);

        $this->assertEqualsWithDelta(0.01, array_sum($shares), 0.00001);
        $this->assertContains(0.01, array_map(fn($v) => round($v, 2), $shares));
    }
}
