<?php

namespace App\Services;

use App\Enums\CompanyFilter;
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

        $company->update([
            'status' => CompanyStatus::Trial,
            'trial_ends_at' => $company->trialEndAfterExtending($days),
        ]);
    }

    /**
     * Number of companies behind each filter card of the companies page, in one query.
     *
     * @return array<string, int> keyed by CompanyFilter value
     */
    public function overview(): array
    {
        $today = today()->toDateString();

        $row = Company::toBase()->selectRaw(
            'count(*) as total,
             sum(status = ? and trial_ends_at >= ?) as running,
             sum(status = ? and trial_ends_at < ?) as expired,
             sum(status = ?) as active,
             sum(status = ?) as suspended',
            [
                CompanyStatus::Trial->value, $today,
                CompanyStatus::Trial->value, $today,
                CompanyStatus::Active->value,
                CompanyStatus::Suspended->value,
            ],
        )->first();

        return [
            CompanyFilter::All->value => (int) $row->total,
            CompanyFilter::Trial->value => (int) $row->running,
            CompanyFilter::Expired->value => (int) $row->expired,
            CompanyFilter::Active->value => (int) $row->active,
            CompanyFilter::Suspended->value => (int) $row->suspended,
        ];
    }
}
