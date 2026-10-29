<?php

namespace Database\Seeders;

use App\Enums\CompanyStatus;
use App\Enums\PartyType;
use App\Enums\Role;
use App\Models\Company;
use App\Services\MovementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Local-only demo data for manual testing. All demo accounts use the password "password".
 * A week of movements is posted through MovementService (golden rule 1), so the dashboards and every
 * report have something to show: purchases, sales, a return, a transfer, and one product running low.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::create([
            'name' => 'شركة النور',
            'currency_symbol' => 'ل.س',
            'status' => CompanyStatus::Trial,
            'trial_ends_at' => today()->addDays(config('wms.trial_days')),
        ]);

        $accounts = [
            ['name' => 'سامر', 'email' => 'owner@demo.test', 'role' => Role::Owner],
            ['name' => 'مستودع المدينة', 'email' => 'city@demo.test', 'role' => Role::Warehouse],
            ['name' => 'مستودع الريف', 'email' => 'rural@demo.test', 'role' => Role::Warehouse],
        ];

        $users = [];

        foreach ($accounts as $account) {
            $users[$account['email']] = $company->users()->create($account + ['password' => 'password']);
        }

        $products = [
            'oil' => ['name' => 'زيت زيتون', 'barcode' => '6290001000011', 'unit' => 'liter', 'location' => 'رف A1', 'min_stock' => '20'],
            'sugar' => ['name' => 'سكر', 'barcode' => '6290001000028', 'unit' => 'kilo', 'location' => 'رف A2', 'min_stock' => '50.500'],
            'rice' => ['name' => 'رز مصري', 'barcode' => '6290001000035', 'unit' => 'kilo', 'location' => 'رف B1'],
            'beans' => ['name' => 'معلبات فول', 'barcode' => '6290001000042', 'unit' => 'box', 'location' => 'رف B3', 'min_stock' => '12'],
            'tissues' => ['name' => 'مناديل ورقية', 'unit' => 'piece', 'custom_sale_price' => '15.00'],
            'tea' => ['name' => 'شاي أخضر (متوقف)', 'barcode' => '6290001000059', 'unit' => 'box', 'is_active' => false],
        ];

        foreach ($products as $key => $product) {
            $products[$key] = $company->products()->create($product);
        }

        $parties = [
            'mohammad' => ['name' => 'محمد الأحمد', 'phone' => '0944 111 222', 'type' => PartyType::Customer],
            'yasmin' => ['name' => 'مطعم الياسمين', 'phone' => '011 222 3344', 'type' => PartyType::Customer],
            'sham' => ['name' => 'شركة الشام للتوريد', 'phone' => '0933 555 666', 'type' => PartyType::Supplier],
            'wholesale' => ['name' => 'مورد الجملة', 'type' => PartyType::Supplier],
            'old' => ['name' => 'عميل قديم (متوقف)', 'type' => PartyType::Customer, 'is_active' => false],
        ];

        foreach ($parties as $key => $party) {
            $parties[$key] = $company->parties()->create($party);
        }

        $this->movements($users, $products, $parties);
    }

    /**
     * A week of work in both warehouses, posted as the owner.
     *
     * @param  array<string, \App\Models\User>  $users
     * @param  array<string, \App\Models\Product>  $products
     * @param  array<string, \App\Models\Party>  $parties
     */
    private function movements(array $users, array $products, array $parties): void
    {
        $owner = $users['owner@demo.test'];
        $city = $users['city@demo.test'];
        $rural = $users['rural@demo.test'];
        $service = app(MovementService::class);
        $day = fn (int $daysAgo) => today()->subDays($daysAgo)->toDateString();
        $line = fn (string $product, string $quantity, ?string $price = null) => array_filter([
            'product_id' => $products[$product]->id, 'quantity' => $quantity, 'unit_price' => $price,
        ], fn ($value) => $value !== null);

        Auth::setUser($owner);

        $service->purchase($owner, [
            'warehouse_id' => $city->id, 'party_id' => $parties['sham']->id, 'date' => $day(6), 'document_no' => 'SH-2291', 'discount' => '5000',
            'items' => [$line('oil', '100', '1000'), $line('sugar', '80', '500'), $line('rice', '60', '700'), $line('beans', '30', '2000')],
        ]);

        $service->purchase($owner, [
            'warehouse_id' => $rural->id, 'party_id' => $parties['wholesale']->id, 'date' => $day(5),
            'items' => [$line('oil', '30', '1050'), $line('sugar', '40', '520')],
        ]);

        $sale = $service->sale($owner, [
            'warehouse_id' => $city->id, 'party_id' => $parties['mohammad']->id, 'date' => $day(3), 'discount' => '900',
            'items' => [$line('oil', '6', '1500'), $line('sugar', '10', '750')],
        ]);

        $service->return($owner, $sale, ['date' => $day(2), 'items' => [$line('oil', '2')]]);

        $service->transfer($owner, [
            'warehouse_id' => $city->id, 'target_warehouse_id' => $rural->id, 'date' => $day(1),
            'items' => [$line('oil', '20')],
        ]);

        // Today: a cash sale in the rural warehouse (sugar there drops under its minimum) and a sale in the city.
        $service->sale($owner, ['warehouse_id' => $rural->id, 'date' => $day(0), 'items' => [$line('sugar', '5', '780')]]);
        $service->sale($owner, [
            'warehouse_id' => $city->id, 'party_id' => $parties['yasmin']->id, 'date' => $day(0),
            'items' => [$line('rice', '20', '1050'), $line('beans', '4', '3000')],
        ]);

        Auth::forgetUser();
    }
}
