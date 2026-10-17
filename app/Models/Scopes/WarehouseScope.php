<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Warehouse isolation (SRS 4.1): a warehouse user sees only their own warehouse's rows.
 * Owners and the platform owner are not narrowed here (the company scope still applies).
 * Movements pass `includeTarget` so the receiving warehouse also sees incoming transfers.
 */
class WarehouseScope implements Scope
{
    public function __construct(private readonly bool $includeTarget = false) {}

    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user?->isWarehouse()) {
            return;
        }

        $builder->where(function (Builder $query) use ($model, $user) {
            $query->where($model->qualifyColumn('warehouse_id'), $user->id);

            if ($this->includeTarget) {
                $query->orWhere($model->qualifyColumn('target_warehouse_id'), $user->id);
            }
        });
    }
}
