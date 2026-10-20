<?php

namespace App\Enums;

/**
 * Filters of the stock report (SRS 3.6.6).
 */
enum StockFilter: string
{
    case BelowMin = 'below_min';
    case Moved = 'moved';
    case Stagnant = 'stagnant';

    public function label(): string
    {
        return match ($this) {
            self::BelowMin => 'المواد المنخفضة',
            self::Moved => 'المواد المتحركة',
            self::Stagnant => 'المواد الراكدة',
        };
    }
}
