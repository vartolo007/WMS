<?php

namespace App\Policies;

use App\Models\User;

/**
 * Reports are open to the owner and to warehouse users; a warehouse user's reports are limited to their own
 * warehouse by the query itself (ReportFilters::forUser and the Operation scopes), not here.
 * Registered for the ReportKind enum in AppServiceProvider.
 */
class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isWarehouse();
    }
}
