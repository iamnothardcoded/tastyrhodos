<?php

declare(strict_types=1);

namespace Iamnothardcoded\FoodLabels\Classes;

/**
 * Per-dish food-information workflow state (menus.food_info_status).
 *
 * unknown   — no vetted data; the storefront shows an explicit "no data yet,
 *             please ask us" fallback and NEVER renders attached data
 *             (partial data must not masquerade as complete — Art. 7 LMIV).
 * declared  — data transcribed from owner-provided material (menu footnotes,
 *             Kladde, platform listing) but not yet verified by the owner.
 * confirmed — owner reviewed and confirmed the data.
 *
 * declared and confirmed render identically to customers; the distinction is
 * workflow-only (onboarding handoff vs. owner sign-off).
 */
class InfoStatus
{
    public const UNKNOWN = 'unknown';

    public const DECLARED = 'declared';

    public const CONFIRMED = 'confirmed';

    /** code => lang key suffix */
    public const ALL = [
        self::UNKNOWN => 'status_unknown',
        self::DECLARED => 'status_declared',
        self::CONFIRMED => 'status_confirmed',
    ];

    /** @return string[] valid status codes */
    public static function codes(): array
    {
        return array_keys(self::ALL);
    }

    /** @return array<string, string> code => translated label (for select) */
    public static function options(): array
    {
        $options = [];
        foreach (self::ALL as $code => $langKey) {
            $options[$code] = lang('iamnothardcoded.foodlabels::default.'.$langKey);
        }

        return $options;
    }

    /** Does this status publish data to customers? */
    public static function publishes(?string $status): bool
    {
        return in_array($status, [self::DECLARED, self::CONFIRMED], true);
    }
}
