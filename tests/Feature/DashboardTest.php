<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Services\MovementService;

beforeEach(function () {
    $this->company = Company::factory()->create(['currency_symbol' => 'ل.س', 'status' => CompanyStatus::Trial, 'trial_ends_at' => today()->addDays(9)]);
    $this->owner = User::factory()->for($this->company)->create();
    $this->city = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع المدينة']);
    $this->rural = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع الريف']);
    $supplier = Party::factory()->supplier()->for($this->company)->create(['name' => 'مورد الشام']);
    $customer = Party::factory()->for($this->company)->create(['name' => 'سامر الحلبي']);
    $this->oil = Product::factory()->for($this->company)->create(['name' => 'زيت زيتون', 'unit' => 'liter', 'min_stock' => '20', 'location' => 'A3']);
    $this->sugar = Product::factory()->for($this->company)->create(['name' => 'سكر', 'unit' => 'kilo']);

    $this->actingAs($this->owner);
    $service = app(MovementService::class);
    $today = today()->toDateString();

    $buy = fn (User $wh, Product $p, string $qty) => $service->purchase($this->owner, ['warehouse_id' => $wh->id, 'party_id' => $supplier->id, 'date' => $today, 'items' => [['product_id' => $p->id, 'quantity' => $qty, 'unit_price' => '1000']]]);

    $buy($this->city, $this->oil, '100');
    $buy($this->rural, $this->oil, '10');   // below its minimum of 20 in the rural warehouse
    $buy($this->rural, $this->sugar, '5');  // bought, never sold: stagnant

    // 6,000 with a 600 discount → 5,400; 2 liters come back → 1,800
    $sale = $service->sale($this->owner, ['warehouse_id' => $this->city->id, 'party_id' => $customer->id, 'date' => $today, 'discount' => '600', 'items' => [['product_id' => $this->oil->id, 'quantity' => '6', 'unit_price' => '1000']]]);
    $service->return($this->owner, $sale, ['date' => $today, 'items' => [['product_id' => $this->oil->id, 'quantity' => '2']]]);
});

test('the owner sees net sales per warehouse and in total, low stock, stagnant items and the subscription', function () {
    $page = $this->get('/dashboard')->assertOk();

    $page->assertViewHas('sales', fn ($sales) => $sales['totals']['net'] === '3600.00'
        && $sales['totals']['sales'] === '5400.00'
        && $sales['totals']['returns'] === '1800.00'
        && $sales['rows']->pluck('net')->sort()->values()->all() === ['0.00', '3600.00']);

    $page->assertViewHas('lowCount', 1)
        ->assertViewHas('stagnantCount', 1)
        ->assertViewHas('movementCount', 5)
        ->assertSee('3,600.00')
        ->assertSee('مستودع الريف')
        ->assertSee('مواد منخفضة المخزون')
        ->assertSee('الاشتراك')
        ->assertSee('باقي')
        ->assertSee('مواد راكدة');
});

test('choosing a warehouse in the top bar narrows every number', function () {
    $this->withSession(['warehouse_id' => $this->rural->id]);

    $this->get('/dashboard')->assertOk()
        ->assertViewHas('sales', fn ($sales) => $sales['totals']['net'] === '0.00' && $sales['rows']->count() === 1)
        ->assertViewHas('movementCount', 2)
        ->assertViewHas('lowCount', 1)
        // in the rural warehouse neither its oil nor its sugar was sold; company wide the oil was (in the city)
        ->assertViewHas('stagnantCount', 2);

    $this->withSession(['warehouse_id' => $this->city->id])->get('/dashboard')->assertOk()
        ->assertViewHas('sales', fn ($sales) => $sales['totals']['net'] === '3600.00')
        ->assertViewHas('lowCount', 0)
        ->assertViewHas('stagnantCount', 0)
        ->assertSee('كل المواد فوق حدها الأدنى');
});

test('an active company shows no trial progress', function () {
    $this->company->update(['status' => CompanyStatus::Active]);

    $this->get('/dashboard')->assertOk()->assertSee('اشتراك شركتك فعّال')->assertDontSee('باقي');
});

test('an owner without warehouses is asked to add one', function () {
    $lonely = User::factory()->for(Company::factory()->create())->create();

    $this->actingAs($lonely)->get('/dashboard')->assertOk()->assertSee('أضف أول مستودع');
});

test('a warehouse user sees only their own numbers and movements', function () {
    $this->actingAs($this->city)->get('/dashboard')->assertOk()
        ->assertViewHas('sales', fn ($sales) => $sales['net'] === '3600.00')
        ->assertViewHas('movementCount', 3)
        ->assertViewHas('movementBreakdown', '1 شراء · 1 بيع · 1 مرتجع بيع')
        ->assertViewHas('lowCount', 0)
        ->assertSee('S-0001')
        ->assertSee('SR-0001')
        ->assertSee('P-0001')
        ->assertDontSee('P-0002');

    $rural = $this->actingAs($this->rural)->get('/dashboard')->assertOk()
        ->assertViewHas('sales', fn ($sales) => $sales['net'] === '0.00')
        ->assertViewHas('lowCount', 1)
        ->assertSee('زيت زيتون')
        ->assertSee('A3')
        ->assertDontSee('S-0001');

    expect($rural->viewData('low')->first()['remaining'])->toBe('10.000');
});

test('a quiet day shows zeros and no movements', function () {
    $quiet = User::factory()->warehouse()->for($this->company)->create();

    $this->actingAs($quiet)->get('/dashboard')->assertOk()
        ->assertViewHas('movementCount', 0)
        ->assertSee('ما في حركات اليوم');
});
