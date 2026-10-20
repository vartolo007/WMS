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
     * Cancel a confirmed movement (FR-17): the owner, or the warehouse that owns it (for a transfer, the sending one;
     * the receiving warehouse can see it but not cancel it).
     */
    public function cancel(User $user, Movement $movement): bool
    {
        return $this->view($user, $movement)
            && $movement->status === MovementStatus::Confirmed
            && ($user->isOwner() || ($user->isWarehouse() && $movement->warehouse_id === $user->id));
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
