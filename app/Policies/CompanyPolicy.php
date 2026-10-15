<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

/**
 * Only the owner of a company changes its identity and pricing settings (FR-25, SRS 11).
 */
class CompanyPolicy
{
    public function updateSettings(User $user, Company $company): bool
    {
        return $user->isOwner() && $user->company_id === $company->id;
    }
}
