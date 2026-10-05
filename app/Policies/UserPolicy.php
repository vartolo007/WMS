<?php

namespace App\Policies;

use App\Models\User;

/**
 * Warehouse accounts are managed only by the owner of the same company (FR-05, FR-06).
 */
class UserPolicy
{
    public function manageWarehouses(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, User $warehouse): bool
    {
        return $user->isOwner()
            && $warehouse->isWarehouse()
            && $warehouse->company_id === $user->company_id;
    }
}
