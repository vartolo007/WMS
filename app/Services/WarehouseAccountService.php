<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;

/**
 * Warehouse accounts: one user per warehouse, never deleted (FR-05, T12).
 */
class WarehouseAccountService
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function create(Company $company, array $data): User
    {
        return $company->users()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => Role::Warehouse,
        ]);
    }

    /**
     * The password changes only when a new one is given.
     *
     * @param  array{name: string, email: string, password?: string|null}  $data
     */
    public function update(User $warehouse, array $data): void
    {
        $warehouse->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (filled($data['password'] ?? null)) {
            $warehouse->password = $data['password'];
        }

        $warehouse->save();
    }

    public function setActive(User $warehouse, bool $active): void
    {
        $warehouse->update(['is_active' => $active]);
    }
}
