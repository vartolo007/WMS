<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Enums\Role;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'trial_ends_at',
        'markup_percent',
        'logo_path',
        'primary_color',
        'currency_symbol',
    ];

    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
            'trial_ends_at' => 'date',
            'markup_percent' => 'decimal:2',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function warehouses(): HasMany
    {
        return $this->users()->where('role', Role::Warehouse);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function parties(): HasMany
    {
        return $this->hasMany(Party::class);
    }

    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->where('role', Role::Owner);
    }

    /**
     * Public URL of the logo, or null when the company has none. Built from the current request host
     * (`asset`), so it works whatever APP_URL says.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : null;
    }

    /**
     * A trial that has not ended yet (it ends at the end of `trial_ends_at`).
     */
    public function scopeRunningTrial(Builder $query): void
    {
        $query->where('status', CompanyStatus::Trial)->whereDate('trial_ends_at', '>=', today());
    }

    /**
     * A trial whose end date has passed. It stays a trial until the platform owner decides (FR-03).
     */
    public function scopeExpiredTrial(Builder $query): void
    {
        $query->where('status', CompanyStatus::Trial)->whereDate('trial_ends_at', '<', today());
    }

    /**
     * Expired trials first: they are the companies to contact (FR-02).
     */
    public function scopeExpiredFirst(Builder $query): void
    {
        $query->orderByRaw('CASE WHEN status = ? AND trial_ends_at < ? THEN 0 ELSE 1 END', [CompanyStatus::Trial->value, today()->toDateString()]);
    }

    /**
     * Partial match on the company name, or on the name / email of its owner.
     * LIKE wildcards typed by the user are taken literally.
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '\%_').'%';

        $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhereHas('owner', fn (Builder $owner) => $owner->where('name', 'like', $like)->orWhere('email', 'like', $like)));
    }

    /**
     * The trial end date after extending by `$days`: counted from the later of today and the current end date.
     */
    public function trialEndAfterExtending(int $days): CarbonInterface
    {
        return $this->trial_ends_at->max(today())->copy()->addDays($days);
    }

    /**
     * Whole days since the trial ended, never negative (0 while it still runs).
     */
    public function trialDaysOver(): int
    {
        return max(0, (int) $this->trial_ends_at->diffInDays(today(), false));
    }

    public function isTrialExpired(): bool
    {
        return $this->status === CompanyStatus::Trial && $this->trial_ends_at->lt(today());
    }

    /**
     * Whole days left in the trial, never negative (0 on the last day and after).
     */
    public function trialDaysLeft(): int
    {
        return max(0, (int) today()->diffInDays($this->trial_ends_at, false));
    }
}
