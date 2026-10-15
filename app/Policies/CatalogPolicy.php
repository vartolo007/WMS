<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Q2 (open question): owner and warehouse users both manage the shared catalogues (products, parties).
 * Every ability goes through manage(), so flipping the answer is a one-line change for all of them.
 */
abstract class CatalogPolicy
{
    public function manage(User $user): bool
    {
        return $user->isOwner() || $user->isWarehouse();
    }

    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->manage($user) && $record->company_id === $user->company_id;
    }
}
