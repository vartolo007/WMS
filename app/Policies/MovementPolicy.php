<?php

namespace App\Policies;

use App\Enums\MovementStatus;
use App\Models\Movement;
use App\Models\User;

/**
 * Owner and warehouse users work with movements. Which rows a warehouse user can reach is decided
 * by the global scopes (own warehouse, incoming transfers); this policy only re-checks the company.
 */
class MovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isWarehouse();
    }

    public function view(User $user, Movement $movement): bool
    {
        return $this->viewAny($user) && $movement->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * A return starts from a confirmed purchase or sale the user can see (FR-15).
     */
    public function createReturn(User $user, Movement $original): bool
    {
        return $this->view($user, $original)
            && $original->type->returnType() !== null
            && $original->status === MovementStatus::Confirmed;
    }
}
