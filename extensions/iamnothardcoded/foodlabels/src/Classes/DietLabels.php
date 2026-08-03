<?php

declare(strict_types=1);

namespace Iamnothardcoded\FoodLabels\Classes;

/**
 * Diet/attribute badges shown on menu item rows. Codes are stable (stored on
 * menus.diet_labels as a JSON array, shared with import tooling); a dish can
 * carry several (e.g. vegan + hot).
 */
class DietLabels
{
    /** code => lang key suffix */
    public const ALL = [
        'veg' => 'diet_veg',
        'vegan' => 'diet_vegan',
        'hot' => 'diet_hot',
    ];

    /** @return string[] valid diet codes */
    public static function codes(): array
    {
        return array_keys(self::ALL);
    }

    /** @return array<string, string> code => translated label (for checkboxlist) */
    public static function options(): array
    {
        $options = [];
        foreach (self::ALL as $code => $langKey) {
            $options[$code] = lang('iamnothardcoded.foodlabels::default.'.$langKey);
        }

        return $options;
    }

    /**
     * Codes to actually render, in canonical order. Display rule: vegan
     * implies vegetarian, so a vegan dish shows only the vegan badge.
     *
     * @return string[]
     */
    public static function displayCodes(array $codes): array
    {
        $codes = array_values(array_intersect(self::codes(), $codes));
        if (in_array('vegan', $codes, true)) {
            $codes = array_values(array_diff($codes, ['veg']));
        }

        return $codes;
    }

    /**
     * Codes for client-side filter MATCHING, canonical order. Inverse of the
     * display rule: vegan counts as vegetarian, so vegan implies +veg — a
     * "vegetarian" filter must include vegan dishes.
     *
     * @return string[]
     */
    public static function filterCodes(array $codes): array
    {
        $codes = array_values(array_intersect(self::codes(), $codes));
        if (in_array('vegan', $codes, true) && !in_array('veg', $codes, true)) {
            $codes[] = 'veg';
        }

        return array_values(array_intersect(self::codes(), $codes));
    }
}
