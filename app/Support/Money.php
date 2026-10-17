<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Every money and quantity calculation goes through here (golden rule 3, T9).
 * Values are decimal strings in and out; PHP floats never touch them.
 * Quantity scale 3, money scale 2, rounding HALF_UP.
 */
final class Money
{
    public const QUANTITY_SCALE = 3;

    public const MONEY_SCALE = 2;

    /**
     * Value of one line: quantity × unit price, rounded to 2 decimals. 1.25 × 19999.50 = 24999.38.
     */
    public static function line(string $quantity, string $unitPrice): string
    {
        return (string) BigDecimal::of($quantity)
            ->multipliedBy($unitPrice)
            ->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);
    }

    /**
     * Sum of money values, 2 decimals.
     *
     * @param  iterable<string>  $values
     */
    public static function sum(iterable $values): string
    {
        $total = BigDecimal::zero();

        foreach ($values as $value) {
            $total = $total->plus($value);
        }

        return (string) $total->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);
    }

    /**
     * Sum of quantities, 3 decimals.
     *
     * @param  iterable<string>  $values
     */
    public static function sumQuantity(iterable $values): string
    {
        $total = BigDecimal::zero();

        foreach ($values as $value) {
            $total = $total->plus($value);
        }

        return (string) $total->toScale(self::QUANTITY_SCALE, RoundingMode::HALF_UP);
    }

    public static function subtract(string $a, string $b): string
    {
        return (string) BigDecimal::of($a)->minus($b)->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);
    }

    public static function subtractQuantity(string $a, string $b): string
    {
        return (string) BigDecimal::of($a)->minus($b)->toScale(self::QUANTITY_SCALE, RoundingMode::HALF_UP);
    }

    /**
     * A share of the movement net: value × total ÷ subtotal, rounded once at the end (SRS 6.2).
     * With a zero subtotal there is nothing to share, so the value stays as it is.
     */
    public static function ratio(string $value, string $total, string $subtotal): string
    {
        if (BigDecimal::of($subtotal)->isZero()) {
            return (string) BigDecimal::of($value)->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);
        }

        return (string) BigDecimal::of($value)
            ->multipliedBy($total)
            ->dividedBy($subtotal, self::MONEY_SCALE, RoundingMode::HALF_UP);
    }

    /**
     * A price scaled by a percentage: price × percent ÷ 100. 1000 at 200% = 2000.
     */
    public static function percent(string $price, string $percent): string
    {
        return (string) BigDecimal::of($price)
            ->multipliedBy($percent)
            ->dividedBy('100', self::MONEY_SCALE, RoundingMode::HALF_UP);
    }

    /**
     * -1, 0 or 1, comparing numerically (never as strings).
     */
    public static function compare(string $a, string $b): int
    {
        return BigDecimal::of($a)->compareTo($b);
    }

    public static function isPositive(string $value): bool
    {
        return BigDecimal::of($value)->isPositive();
    }

    /**
     * The value with exactly 3 decimals, for storing quantities.
     */
    public static function quantity(string $value): string
    {
        return (string) BigDecimal::of($value)->toScale(self::QUANTITY_SCALE, RoundingMode::HALF_UP);
    }

    /**
     * The value with exactly 2 decimals, for storing money.
     */
    public static function money(string $value): string
    {
        return (string) BigDecimal::of($value)->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);
    }
}
