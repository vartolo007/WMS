<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Enums\Role;
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

    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->where('role', Role::Owner);
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
