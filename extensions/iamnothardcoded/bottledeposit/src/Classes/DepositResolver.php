<?php

declare(strict_types=1);

namespace Iamnothardcoded\BottleDeposit\Classes;

use Igniter\Cart\Models\Menu;

/**
 * Resolves menu items' deposit classes, batched + cached per request (the
 * cart condition and the storefront hint both ask for the same items).
 * Item-level only by design: deposits are a property of the container, not
 * of a menu category (a drinks category legitimately mixes all tiers).
 */
class DepositResolver
{
    /** @var array<int, string|null> menu_id => deposit class code (null = none) */
    protected static array $cache = [];

    /**
     * @param int[] $menuIds
     * @return array<int, string|null> menu_id => class code or null
     */
    public static function forMenuIds(array $menuIds): array
    {
        $menuIds = array_map('intval', $menuIds);

        $missing = array_diff($menuIds, array_keys(self::$cache));
        if ($missing !== []) {
            Menu::query()->whereIn('menu_id', $missing)
                ->pluck('deposit_class', 'menu_id')
                ->each(function($class, $menuId): void {
                    self::$cache[(int)$menuId] = ($class !== null && $class !== '') ? (string)$class : null;
                });

            // Deleted/unknown menus still in a cart session carry no deposit.
            foreach ($missing as $id) {
                self::$cache[$id] ??= null;
            }
        }

        return array_intersect_key(self::$cache, array_flip($menuIds));
    }

    public static function amountForMenuId(int $menuId): float
    {
        $class = self::forMenuIds([$menuId])[$menuId] ?? null;

        return DepositClasses::amountFor($class);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
