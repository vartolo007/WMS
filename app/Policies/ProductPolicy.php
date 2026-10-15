<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Q2 (open question): owner and warehouse users both manage the shared catalogue.
 * Every ability goes through manage(), so flipping the answer is a one-line change.
 */
class ProductPolicy
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

    public function update(User $user, Product $product): bool
    {
        return $this->manage($user) && $product->company_id === $user->company_id;
    }
}
