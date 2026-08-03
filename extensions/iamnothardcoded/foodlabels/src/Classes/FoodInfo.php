<?php

declare(strict_types=1);

namespace Iamnothardcoded\FoodLabels\Classes;

use Igniter\Cart\Models\Menu;

/**
 * Builds the per-dish payload for the storefront info popup.
 *
 * States: 'unknown' (no vetted data — the popup shows the ask-us fallback),
 * 'declared' (list allergens + additive formulae), 'none' (data vetted,
 * nothing declarable). An empty list only ever means "none" when the dish's
 * info status publishes — from 'unknown' it must never read as allergen-free.
 * The workflow status (declared vs confirmed) additionally travels in
 * 'status' so the dialog can label the data verified/unverified in color.
 */
class FoodInfo
{
    /** @return array{name: string, state: string, allergens?: string[], additives?: string[]} */
    public static function payload(Menu $menu): array
    {
        $name = (string)$menu->menu_name;

        if (!InfoStatus::publishes($menu->food_info_status ?? null)) {
            return ['name' => $name, 'state' => 'unknown'];
        }

        // The menu list eager-loads the ingredients relation (orange
        // MenuItemList) — filter the collection instead of querying the
        // allergens() relation, which would N+1 across the list.
        $allergens = $menu->ingredients
            ->where('is_allergen', 1)
            ->where('status', 1)
            ->pluck('name')
            ->values()
            ->all();
        $additives = Additives::labelsFor((array)($menu->additives ?? []));

        return [
            'name' => $name,
            'state' => ($allergens !== [] || $additives !== []) ? 'declared' : 'none',
            // workflow status travels separately: the dialog shows a colored
            // verified/unverified line next to the dish name
            'status' => $menu->food_info_status,
            'allergens' => $allergens,
            'additives' => $additives,
        ];
    }
}
