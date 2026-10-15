<?php

namespace App\Enums;

use Illuminate\Database\Eloquent\Builder;

/**
 * The filter cards of the companies page (design screen 19). They double as the platform dashboard (FR-24):
 * counts per status, plus the trials that already ended.
 * "Trial" is a trial still running; "expired" is a trial whose end date has passed (it stays trial until
 * the platform owner decides, FR-03).
 */
enum CompanyFilter: string
{
    case All = 'all';
    case Trial = 'trial';
    case Expired = 'expired';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::All => 'كل الشركات',
            self::Trial => 'تجريبي',
            self::Expired => 'انتهت تجربتها',
            self::Active => 'فعّال',
            self::Suspended => 'موقوف',
        };
    }

    /**
     * Badge tone, reused for the colour of the count.
     */
    public function tone(): string
    {
        return match ($this) {
            self::All => 'neutral',
            self::Trial => 'warning',
            self::Expired, self::Suspended => 'danger',
            self::Active => 'success',
        };
    }

    /**
     * @param  Builder<\App\Models\Company>  $query
     */
    public function apply(Builder $query): void
    {
        match ($this) {
            self::All => null,
            self::Trial => $query->runningTrial(),
            self::Expired => $query->expiredTrial(),
            self::Active => $query->where('status', CompanyStatus::Active),
            self::Suspended => $query->where('status', CompanyStatus::Suspended),
        };
    }
}
