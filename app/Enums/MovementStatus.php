<?php

namespace App\Enums;

enum MovementStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'مؤكدة',
            self::Cancelled => 'ملغاة',
        };
    }
}
