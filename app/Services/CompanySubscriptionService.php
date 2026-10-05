<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Models\Company;
use Illuminate\Validation\ValidationException;

/**
 * Subscription changes made by the platform owner (FR-02).
 */
class CompanySubscriptionService
{
    public function activate(Company $company): void
    {
        $company->update(['status' => CompanyStatus::Active]);
    }

    public function suspend(Company $company): void
    {
        $company->update(['status' => CompanyStatus::Suspended]);
    }

    /**
     * Extend from today or from the current end date, whichever is later, and put the company back on trial.
     * Not allowed for an active (subscribed) company.
     *
     * @throws ValidationException
     */
    public function extendTrial(Company $company, int $days): void
    {
        if ($company->status === CompanyStatus::Active) {
            throw ValidationException::withMessages([
                'days' => 'لا يمكن تمديد التجربة لشركة مشتركة.',
            ]);
        }

        $from = $company->trial_ends_at->max(today())->copy();

        $company->update([
            'status' => CompanyStatus::Trial,
            'trial_ends_at' => $from->addDays($days),
        ]);
    }
}
