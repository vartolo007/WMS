<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompanyRegistrationService
{
    /**
     * Create a trial company and its owner in one transaction (FR-01).
     *
     * @param  array{company_name: string, name: string, email: string, password: string}  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $company = Company::create([
                'name' => $data['company_name'],
                'status' => CompanyStatus::Trial,
                'trial_ends_at' => today()->addDays(config('wms.trial_days')),
            ]);

            return $company->users()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => Role::Owner,
            ]);
        });
    }
}
