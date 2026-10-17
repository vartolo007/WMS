<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;

/**
 * Sidebar items per role, in one place. Only screens that exist are listed;
 * reports join here in their phase.
 *
 * @phpstan-type Item array{label: string, route: string, active: string|list<string>}
 */
class Navigation
{
    /**
     * @return array{main: list<Item>, company: list<Item>}
     */
    public static function for(User $user): array
    {
        $dashboard = self::item('لوحة التحكم', 'dashboard');
        $newMovement = self::item('حركة جديدة', 'movements.create');
        $movements = self::item('الحركات', 'movements.index', ['movements.index', 'movements.show']);
        $products = self::item('دليل المواد', 'products.index', 'products.*');
        $parties = self::item('العملاء والموردون', 'parties.index', 'parties.*');

        return match ($user->role) {
            Role::SuperAdmin => [
                // The companies page is the platform dashboard (FR-24): one item.
                'main' => [self::item('الشركات', 'admin.companies.index', 'admin.companies.*')],
                'company' => [],
            ],
            Role::Owner => [
                'main' => [$dashboard, $newMovement, $movements, $products, $parties],
                'company' => [
                    self::item('المستودعات', 'warehouses.index', 'warehouses.*'),
                    self::item('إعدادات الشركة', 'settings.edit', 'settings.*'),
                ],
            ],
            Role::Warehouse => [
                'main' => [$dashboard, $newMovement, $movements, $products, $parties],
                'company' => [],
            ],
        };
    }

    /**
     * @return Item
     */
    private static function item(string $label, string $route, string|array|null $active = null): array
    {
        return ['label' => $label, 'route' => $route, 'active' => $active ?? $route];
    }
}
