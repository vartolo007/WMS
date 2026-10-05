<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    /**
     * Limit rows to the authenticated user's company.
     * Fail safe: no user, or a user without a company (super admin), sees nothing.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = Auth::user()?->company_id;

        if ($companyId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $companyId);
    }
}
