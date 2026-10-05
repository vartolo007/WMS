<?php

namespace Database\Seeders;

use App\Enums\CompanyStatus;
use App\Enums\Role;
use App\Models\Company;
use Illuminate\Database\Seeder;

/**
 * Local-only demo data for manual testing. All demo accounts use the password "password".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::create([
            'name' => 'شركة النور',
            'status' => CompanyStatus::Trial,
            'trial_ends_at' => today()->addDays(config('wms.trial_days')),
        ]);

        $accounts = [
            ['name' => 'سامر', 'email' => 'owner@demo.test', 'role' => Role::Owner],
            ['name' => 'مستودع المدينة', 'email' => 'city@demo.test', 'role' => Role::Warehouse],
            ['name' => 'مستودع الريف', 'email' => 'rural@demo.test', 'role' => Role::Warehouse],
        ];

        foreach ($accounts as $account) {
            $company->users()->create($account + ['password' => 'password']);
        }
    }
}
