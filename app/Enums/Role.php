<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Warehouse = 'warehouse';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'صاحب الشركة',
            self::Warehouse => 'مستودع',
            self::SuperAdmin => 'مدير المنصة',
        };
    }
}
