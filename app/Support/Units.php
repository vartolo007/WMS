<?php

namespace App\Support;

/**
 * The approved units from `config('wms.units')`. The one place that knows which units accept fractions.
 */
class Units
{
    /**
     * @return array<string, string> key => Arabic label
     */
    public static function options(): array
    {
        return array_map(fn (array $unit) => $unit['label'], config('wms.units'));
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(config('wms.units'));
    }

    public static function label(?string $key): string
    {
        return config("wms.units.{$key}.label", (string) $key);
    }

    /**
     * An unknown unit counts as whole numbers only: the safe side.
     */
    public static function allowsFraction(?string $key): bool
    {
        return (bool) config("wms.units.{$key}.allows_fraction", false);
    }
}
