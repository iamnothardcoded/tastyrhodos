<?php

declare(strict_types=1);

namespace Iamnothardcoded\TaxClasses\Classes;

use Iamnothardcoded\TaxClasses\Models\TaxClassSettings;
use Igniter\Cart\Models\Menu;

/**
 * Resolves a menu item's tax class: item column → first classed category → default.
 * Batched + cached per request because BOTH conditions ask for the same cart's
 * items and CartItem->model would run one uncached find() per access.
 */
class TaxClassResolver
{
    public const CLASSES = ['reduced', 'standard'];

    /** @var array<int, string> menu_id => class */
    protected static array $cache = [];

    /**
     * @param int[] $menuIds
     * @return array<int, string> menu_id => 'reduced'|'standard'
     */
    public static function forMenuIds(array $menuIds): array
    {
        $menuIds = array_map('intval', $menuIds);

        $missing = array_diff($menuIds, array_keys(self::$cache));
        if ($missing !== []) {
            Menu::query()->with('categories')->whereIn('menu_id', $missing)->get()
                ->each(function(Menu $menu): void {
                    self::$cache[(int)$menu->menu_id] = self::resolve($menu);
                });

            // Deleted/unknown menus still in a cart session fall back to the default.
            foreach ($missing as $id) {
                self::$cache[$id] ??= self::defaultClass();
            }
        }

        return array_intersect_key(self::$cache, array_flip($menuIds));
    }

    protected static function resolve(Menu $menu): string
    {
        if (in_array($menu->tax_class, self::CLASSES, true)) {
            return $menu->tax_class;
        }

        // Multi-category conflict: first classed category wins (relation order) —
        // operators pin the class on the item itself in that case.
        $fromCategory = $menu->categories
            ->pluck('tax_class')
            ->first(fn($class): bool => in_array($class, self::CLASSES, true));

        return $fromCategory ?? self::defaultClass();
    }

    public static function defaultClass(): string
    {
        $class = (string)TaxClassSettings::get('default_class', 'reduced');

        return in_array($class, self::CLASSES, true) ? $class : 'reduced';
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
