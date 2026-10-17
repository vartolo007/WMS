<?php

namespace App\Enums;

enum MovementStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function tone(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Cancelled => 'slate',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'مؤكدة',
            self::Cancelled => 'ملغاة',
        };
    }
}
