<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\CompanyStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Not tenant-scoped on purpose (T13): login looks users up before anyone is authenticated.
 * Reach company users through $company->users() and guard access with UserPolicy.
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isOwner(): bool
    {
        return $this->role === Role::Owner;
    }

    public function isWarehouse(): bool
    {
        return $this->role === Role::Warehouse;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    /**
     * Why this user may not use the system right now, as a translation key, or null if allowed.
     * Used by login and by EnsureSubscriptionActive. An expired trial does not block (FR-03).
     */
    public function accessBlockReason(): ?string
    {
        if (! $this->is_active) {
            return 'auth.inactive';
        }

        if (! $this->isSuperAdmin() && $this->company?->status === CompanyStatus::Suspended) {
            return 'auth.suspended';
        }

        return null;
    }
}
