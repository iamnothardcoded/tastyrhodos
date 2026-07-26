<?php

declare(strict_types=1);

namespace Iamnothardcoded\TaxClasses\Classes;

/**
 * Splits a cart-level discount across the per-class gross bases pro-rata.
 * Works in cents with largest-remainder distribution so the shares always sum
 * to exactly the discount (no leaked cent) and, because the discount is capped
 * at the total base, no share can exceed its own base.
 */
class DiscountAllocator
{
    /**
     * @param float $discount positive discount magnitude
     * @param array<string, float> $bases class => gross base
     * @return array<string, float> class => share of the discount
     */
    public static function allocate(float $discount, array $bases): array
    {
        $shares = array_map(fn(): int => 0, $bases);

        $total = array_sum($bases);
        if ($discount <= 0 || $total <= 0) {
            return array_map(fn(): float => 0.0, $shares);
        }

        // A discount can't reverse more tax than the items ever contained.
        $discountCents = (int)round(min($discount, $total) * 100);

        $remainders = [];
        $allocated = 0;
        foreach ($bases as $class => $base) {
            $raw = $discountCents * $base / $total;
            $floor = (int)floor($raw);
            $shares[$class] = $floor;
            $remainders[$class] = $raw - $floor;
            $allocated += $floor;
        }

        // Largest remainder first; PHP sorts are stable (ties keep array order).
        arsort($remainders);
        $left = $discountCents - $allocated;
        foreach (array_keys($remainders) as $class) {
            if ($left <= 0) {
                break;
            }

            $shares[$class]++;
            $left--;
        }

        return array_map(fn(int $cents): float => $cents / 100, $shares);
    }
}
