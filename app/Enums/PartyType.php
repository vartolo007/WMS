<?php

namespace App\Enums;

enum PartyType: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'عميل',
            self::Supplier => 'مورد',
        };
    }

    /**
     * Tab label on the parties screen.
     */
    public function plural(): string
    {
        return match ($this) {
            self::Customer => 'العملاء',
            self::Supplier => 'الموردون',
        };
    }
}
