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

    /**
     * Badge tone (design batch 1: trial amber, active green, suspended red).
     */
    public function tone(): string
    {
        return match ($this) {
            self::Trial => 'warning',
            self::Active => 'success',
            self::Suspended => 'danger',
        };
    }
}
