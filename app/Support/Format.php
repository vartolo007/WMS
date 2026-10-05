<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeInterface;

/**
 * Display formatting only. Values stay decimal strings end to end (golden rule 3):
 * no float conversion, so 24999.375 can never turn into 24999.37 by binary rounding.
 */
class Format
{
    private const MONTHS = [
        1 => 'كانون الثاني', 'شباط', 'آذار', 'نيسان', 'أيار', 'حزيران',
        'تموز', 'آب', 'أيلول', 'تشرين الأول', 'تشرين الثاني', 'كانون الأول',
    ];

    private const WEEKDAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

    /**
     * 24999.375 → "24,999.38" (+ " ل.س" when a symbol is given).
     */
    public static function money(string|int|null $amount, ?string $symbol = null): string
    {
        $formatted = self::number($amount ?? '0', 2);

        return filled($symbol) ? "{$formatted} {$symbol}" : $formatted;
    }

    /**
     * 1.25 → "1.250".
     */
    public static function quantity(string|int|null $quantity): string
    {
        return self::number($quantity ?? '0', 3);
    }

    /**
     * "5 تشرين الأول 2026", or "الأحد 5 تشرين الأول 2026" with the weekday.
     */
    public static function date(DateTimeInterface $date, bool $withWeekday = false): string
    {
        $text = $date->format('j').' '.self::MONTHS[(int) $date->format('n')].' '.$date->format('Y');

        return $withWeekday ? self::WEEKDAYS[(int) $date->format('w')].' '.$text : $text;
    }

    private static function number(string|int $value, int $scale): string
    {
        $decimal = BigDecimal::of($value)->toScale($scale, RoundingMode::HALF_UP);

        $negative = $decimal->isNegative();
        [$integer, $fraction] = explode('.', (string) $decimal->abs());

        $grouped = strrev(implode(',', str_split(strrev($integer), 3)));

        return ($negative ? '-' : '').$grouped.'.'.$fraction;
    }
}
