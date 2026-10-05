<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The warehouse the current screen works on.
 * - warehouse user: always their own warehouse.
 * - owner: the one picked in the top bar (kept in the session), or null for "all warehouses".
 * - anyone else: null.
 * Creating a movement needs a concrete warehouse, so callers must not accept null there.
 */
class CurrentWarehouse
{
    private const SESSION_KEY = 'warehouse_id';

    public function get(): ?User
    {
        $user = Auth::user();

        if ($user?->isWarehouse()) {
            return $user;
        }

        if (! $user?->isOwner() || ! session()->has(self::SESSION_KEY)) {
            return null;
        }

        // Re-check on every read: the session value must still be a warehouse of this company.
        return $user->company->warehouses()->find(session(self::SESSION_KEY));
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    public function set(?int $warehouseId): void
    {
        $warehouseId === null
            ? session()->forget(self::SESSION_KEY)
            : session()->put(self::SESSION_KEY, $warehouseId);
    }
}
