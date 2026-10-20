<?php

use App\Enums\ReportKind;
use App\Models\Company;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Services\MovementService;

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'شركة النور', 'currency_symbol' => 'ل.س']);
    $this->owner = User::factory()->for($this->company)->create();
    $this->city = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع المدينة']);
    $this->rural = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع الريف']);
    $this->supplier = Party::factory()->supplier()->for($this->company)->create(['name' => 'مورد الشام']);
    $this->customer = Party::factory()->for($this->company)->create(['name' => 'سامر الحلبي']);
    $this->oil = Product::factory()->for($this->company)->create(['name' => 'زيت زيتون', 'unit' => 'liter', 'min_stock' => '20']);

    $this->actingAs($this->owner);
    $service = app(MovementService::class);
    $today = today()->toDateString();

    $service->purchase($this->owner, ['warehouse_id' => $this->city->id, 'party_id' => $this->supplier->id, 'date' => $today, 'items' => [['product_id' => $this->oil->id, 'quantity' => '100', 'unit_price' => '1000']]]);
    $service->purchase($this->owner, ['warehouse_id' => $this->rural->id, 'party_id' => $this->supplier->id, 'date' => $today, 'items' => [['product_id' => $this->oil->id, 'quantity' => '10', 'unit_price' => '1000']]]);
    // 6,000 with a 600 discount → paid 5,400; 2 liters (2,000 → 1,800) come back
    $this->sale = $service->sale($this->owner, ['warehouse_id' => $this->city->id, 'party_id' => $this->customer->id, 'date' => $today, 'discount' => '600', 'items' => [['product_id' => $this->oil->id, 'quantity' => '6', 'unit_price' => '1000']]]);
    $service->return($this->owner, $this->sale, ['date' => $today, 'items' => [['product_id' => $this->oil->id, 'quantity' => '2']]]);
});

test('guests are sent to the login page and the platform owner has no reports', function () {
    auth()->logout();
    $this->get('/reports')->assertRedirect('/login');

    $this->actingAs(User::factory()->superAdmin()->create());
    $this->get('/reports/stock')->assertForbidden();
});

test('the centre opens on the daily sales report and lists every report', function () {
    $this->get('/reports')->assertRedirect(route('reports.show', ReportKind::DailySales));

    $page = $this->get('/reports/daily-sales')->assertOk();

    foreach (ReportKind::cases() as $kind) {
        $page->assertSee($kind->label());
    }
});

test('daily net sales per warehouse show sales, returns and the net after discounts', function () {
    // net: 5,400 − 1,800 (2 of the 6 liters: 2,000 × 5,400 ÷ 6,000) = 3,600
    $this->get('/reports/daily-sales')
        ->assertOk()
        ->assertSee('مستودع المدينة')
        ->assertSee('5,400.00')
        ->assertSee('1,800.00')
        ->assertSee('3,600.00');
});

test('every report renders for the owner, with and without a choice', function () {
    foreach (ReportKind::cases() as $kind) {
        $query = match ($kind) {
            ReportKind::ProductLedger, ReportKind::SalesByProduct => ['product_id' => $this->oil->id],
            ReportKind::PurchasesBySupplier => ['party_id' => $this->supplier->id],
            ReportKind::PartyMovement => ['party_id' => $this->customer->id],
            default => [],
        };

        $this->get(route('reports.show', ['report' => $kind, ...$query]))->assertOk();
        $this->get(route('reports.print', ['report' => $kind, ...$query]))->assertOk()->assertSee('شركة النور')->assertSee($kind->label());
    }
});

test('a report that needs a product or a party asks for it', function () {
    $this->get('/reports/product-ledger')->assertOk()->assertSee('اختر المادة');
    $this->get('/reports/party-movement')->assertOk()->assertSee('اختر');
    $this->get('/reports/purchases-by-supplier')->assertOk()->assertSee('اختر');
});

test('the ledger, the party report and the stock reports show the hand-calculated numbers', function () {
    $this->get('/reports/product-ledger?product_id='.$this->oil->id)->assertOk()->assertSee('P-0001')->assertSee('S-0001')->assertSee('SR-0001')->assertSee('106.000');

    // invoices 5,400, returns 1,800 → net dealing 3,600
    $this->get('/reports/party-movement?party_id='.$this->customer->id)->assertOk()->assertSee('5,400.00')->assertSee('1,800.00')->assertSee('3,600.00');

    // the city warehouse holds 100 − 6 + 2 = 96, the rural one 10 (below its minimum of 20)
    $this->get('/reports/low-stock?split=1')->assertOk()->assertSee('مستودع الريف')->assertSee('10.000')->assertDontSee('96.000');
    $this->get('/reports/stock')->assertOk()->assertSee('106.000');
});

test('the filters are validated and cannot point at another company', function () {
    $foreign = Product::factory()->for(Company::factory()->create())->create();

    $this->get('/reports/stock?to=not-a-date')->assertSessionHasErrors('to');
    $this->get('/reports/stock?from=2026-10-10&to=2026-10-01')->assertSessionHasErrors('to');
    $this->get('/reports/product-ledger?product_id='.$foreign->id)->assertSessionHasErrors('product_id');
    $this->get('/reports/stock?warehouse_id='.$this->owner->id)->assertSessionHasErrors('warehouse_id');
    $this->get('/reports/sales-total?group=nonsense')->assertSessionHasErrors('group');
    $this->get('/reports/not-a-report')->assertNotFound();
});

test('a warehouse user gets reports of their own warehouse only and no warehouse filter', function () {
    $this->actingAs($this->rural);

    $this->get('/reports/daily-sales')->assertOk()->assertSee('مستودع الريف')->assertDontSee('مستودع المدينة')->assertDontSee('كل المستودعات');
    // asking for the other warehouse changes nothing: the query is forced to their own
    $this->get('/reports/stock?warehouse_id='.$this->city->id)->assertOk()->assertSee('10.000')->assertDontSee('96.000');
    $page = $this->get('/reports/product-ledger?product_id='.$this->oil->id)->assertOk();
    $page->assertSee('P-0002')->assertDontSee('P-0001')->assertDontSee('S-0001');
});

test('the sidebar of both roles links to the reports', function () {
    foreach ([$this->owner, $this->city] as $user) {
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee(route('reports.index'), false);
    }
});

test('the products list can show only the low ones, in the warehouse being viewed', function () {
    $service = app(MovementService::class);
    $fine = Product::factory()->for($this->company)->create(['name' => 'مادة وافرة', 'unit' => 'piece', 'min_stock' => '5']);
    $service->purchase($this->owner, ['warehouse_id' => $this->city->id, 'party_id' => $this->supplier->id, 'items' => [['product_id' => $fine->id, 'quantity' => '50', 'unit_price' => '10']]]);

    $names = fn ($response) => $response->assertOk()->viewData('products')->pluck('name')->sort()->values()->all();

    // company wide: oil 96 + 10 = 106 (min 20) is fine, the plentiful one too → nothing is low
    expect($names($this->get('/products?low=1')))->toBe([]);

    // in the rural warehouse oil is 10 ≤ 20 and the plentiful one is 0 ≤ 5; in the city both are fine
    expect($names($this->withSession(['warehouse_id' => $this->rural->id])->get('/products?low=1')))->toBe(['زيت زيتون', 'مادة وافرة'])
        ->and($names($this->withSession(['warehouse_id' => $this->city->id])->get('/products?low=1')))->toBe([]);

    // without the filter everything is listed
    expect($names($this->get('/products')))->toBe(['زيت زيتون', 'مادة وافرة']);
});

test('a warehouse user filters by their own warehouse', function () {
    $names = fn ($response) => $response->assertOk()->viewData('products')->pluck('name')->all();

    expect($names($this->actingAs($this->rural)->get('/products?low=1')))->toBe(['زيت زيتون'])
        ->and($names($this->actingAs($this->city)->get('/products?low=1')))->toBe([]);
});

test('every party row links to its movement report', function () {
    $link = route('reports.show', ['report' => ReportKind::PartyMovement, 'party_id' => $this->customer->id]);

    $this->get('/parties')->assertOk()->assertSee(e($link), false)->assertSee('حركته');
    $this->get($link)->assertOk()->assertSee('سامر الحلبي');
});
