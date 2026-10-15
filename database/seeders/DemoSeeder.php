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

        $products = [
            ['name' => 'زيت زيتون', 'barcode' => '6290001000011', 'unit' => 'liter', 'location' => 'رف A1', 'min_stock' => '20'],
            ['name' => 'سكر', 'barcode' => '6290001000028', 'unit' => 'kilo', 'location' => 'رف A2', 'min_stock' => '50.500'],
            ['name' => 'رز مصري', 'barcode' => '6290001000035', 'unit' => 'kilo', 'location' => 'رف B1'],
            ['name' => 'معلبات فول', 'barcode' => '6290001000042', 'unit' => 'box', 'location' => 'رف B3', 'min_stock' => '12'],
            ['name' => 'مناديل ورقية', 'unit' => 'piece', 'custom_sale_price' => '15.00'],
            ['name' => 'شاي أخضر (متوقف)', 'barcode' => '6290001000059', 'unit' => 'box', 'is_active' => false],
        ];

        foreach ($products as $product) {
            $company->products()->create($product);
        }
    }
}
