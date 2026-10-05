<?php

namespace App\Enums;

enum CompanyStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'تجريبية',
            self::Active => 'فعّالة',
            self::Suspended => 'موقوفة',
        };
    }
}
