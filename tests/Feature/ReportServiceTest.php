<?php

use App\Enums\MovementType;
use App\Enums\StockFilter;
use App\Models\Company;
use App\Models\Movement;
use App\Models\MovementItem;
use App\Models\Operation;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Services\MovementService;
use App\Services\ReportService;
use App\Support\ReportFilters;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->owner = User::factory()->for($this->company)->create();
    $this->city = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع المدينة']);
    $this->rural = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع الريف']);
    $this->supplier = Party::factory()->supplier()->for($this->company)->create(['name' => 'مورد الشام']);
    $this->customer = Party::factory()->for($this->company)->create(['name' => 'سامر']);
    $this->oil = Product::factory()->for($this->company)->create(['name' => 'زيت', 'unit' => 'liter', 'min_stock' => '20']);
    $this->rice = Product::factory()->for($this->company)->create(['name' => 'رز', 'unit' => 'kilo', 'min_stock' => '50']);
    $this->sugar = Product::factory()->for($this->company)->create(['name' => 'سكر', 'unit' => 'kilo']);

    $this->actingAs($this->owner);
    $this->reports = app(ReportService::class);
    $service = app(MovementService::class);

    $this->buy = fn (User $wh, Product $p, string $qty, string $price, string $date) => $service->purchase($this->owner, [
        'warehouse_id' => $wh->id, 'party_id' => $this->supplier->id, 'date' => $date,
        'items' => [['product_id' => $p->id, 'quantity' => $qty, 'unit_price' => $price]],
    ]);

    $this->sell = fn (User $wh, array $items, string $date, array $extra = []) => $service->sale($this->owner, [
        'warehouse_id' => $wh->id, 'party_id' => $this->customer->id, 'date' => $date, 'items' => $items, ...$extra,
    ]);

    $this->giveBack = fn (Movement $original, array $items, string $date) => $service->return($this->owner, $original, ['date' => $date, 'items' => $items]);

    $this->day = fn (int $daysAgo) => today()->subDays($daysAgo)->toDateString();
});

// --- Ledger (SRS 3.6.4) -----------------------------------------------------------------------------

test('SRS 3.6.4: the ledger starts from the balance before the period', function () {
    // 80 in on 5/2, 25 out on 5/8, 40 in on 5/20, 15 out on 5/25
    ($this->buy)($this->city, $this->oil, '80', '10', '2026-05-02');
    ($this->sell)($this->city, [['product_id' => $this->oil->id, 'quantity' => '25', 'unit_price' => '20']], '2026-05-08');
    ($this->buy)($this->city, $this->oil, '40', '10', '2026-05-20');
    ($this->sell)($this->city, [['product_id' => $this->oil->id, 'quantity' => '15', 'unit_price' => '20']], '2026-05-25');

    $ledger = $this->reports->ledger(new ReportFilters(dateFrom: '2026-05-10', dateTo: '2026-05-31', productId: $this->oil->id));
    $rows = collect($ledger['rows']->items());

    expect($ledger['opening'])->toBe('55.000')
        ->and($rows->pluck('number')->all())->toBe(['P-0002', 'S-0002'])
        ->and($rows->pluck('running')->all())->toBe(['95.000', '80.000'])
        ->and($ledger['in_qty'])->toBe('40.000')
        ->and($ledger['out_qty'])->toBe('15.000')
        ->and($ledger['closing'])->toBe('80.000')
        ->and($ledger['in_value'])->toBe('400.00')
        ->and($ledger['out_value'])->toBe('300.00');
});

test('a ledger without a start date has no opening balance and covers everything', function () {
    ($this->buy)($this->city, $this->oil, '80', '10', '2026-05-02');
    ($this->sell)($this->city, [['product_id' => $this->oil->id, 'quantity' => '25', 'unit_price' => '20']], '2026-05-08');

    $ledger = $this->reports->ledger(new ReportFilters(productId: $this->oil->id));

    expect($ledger['opening'])->toBe('0.000')
        ->and(collect($ledger['rows']->items())->pluck('running')->all())->toBe(['80.000', '55.000'])
        ->and($ledger['closing'])->toBe('55.000');
});

test('a ledger of one warehouse ignores the others', function () {
    ($this->buy)($this->city, $this->oil, '80', '10', '2026-05-02');
    ($this->buy)($this->rural, $this->oil, '7', '10', '2026-05-03');

    $city = $this->reports->ledger(new ReportFilters(productId: $this->oil->id, warehouseId: $this->city->id));
    $company = $this->reports->ledger(new ReportFilters(productId: $this->oil->id));

    expect($city['closing'])->toBe('80.000')->and($company['closing'])->toBe('87.000');
});

test('transfer legs count in the ledger quantities but not in its value totals', function () {
    ($this->buy)($this->city, $this->oil, '100', '10', ($this->day)(3));
    app(MovementService::class)->transfer($this->owner, [
        'warehouse_id' => $this->city->id, 'target_warehouse_id' => $this->rural->id, 'date' => ($this->day)(2),
        'items' => [['product_id' => $this->oil->id, 'quantity' => '40']],
    ]);

    $company = $this->reports->ledger(new ReportFilters(productId: $this->oil->id));
    $city = $this->reports->ledger(new ReportFilters(productId: $this->oil->id, warehouseId: $this->city->id));

    // company wide the transfer is internal: quantities show both legs, the balance is unchanged, no money moved
    expect($company['in_qty'])->toBe('140.000')
        ->and($company['out_qty'])->toBe('40.000')
        ->and($company['closing'])->toBe('100.000')
        ->and($company['in_value'])->toBe('1000.00')
        ->and($company['out_value'])->toBe('0.00')
        // the city warehouse really lost 40 litres, still without any money
        ->and($city['closing'])->toBe('60.000')
        ->and($city['out_value'])->toBe('0.00');
});

// --- Daily net sales (SRS 3.6.3, 6.2) -----------------------------------------------------------------

test('net sales of the day are sales minus returns, after the discounts, per warehouse', function () {
    ($this->buy)($this->city, $this->oil, '100', '1000', ($this->day)(3));
    ($this->buy)($this->city, $this->rice, '100', '1000', ($this->day)(3));
    $today = today()->toDateString();

    // 6,000 + 4,000, discount 1,000 → paid 9,000
    $sale = ($this->sell)($this->city, [
        ['product_id' => $this->oil->id, 'quantity' => '6', 'unit_price' => '1000'],
        ['product_id' => $this->rice->id, 'quantity' => '4', 'unit_price' => '1000'],
    ], $today, ['discount' => '1000']);
    // 2,000 of it comes back → 1,800
    ($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '2']], $today);
    // another day does not count
    ($this->sell)($this->city, [['product_id' => $this->oil->id, 'quantity' => '1', 'unit_price' => '1000']], ($this->day)(1));
    ($this->buy)($this->rural, $this->oil, '5', '10', $today);

    $daily = $this->reports->dailySales($today, collect([$this->city, $this->rural]));
    [$city, $rural] = $daily['rows']->all();

    expect($city['invoices'])->toBe(1)
        ->and($city['sales'])->toBe('9000.00')
        ->and($city['returns'])->toBe('1800.00')
        ->and($city['net'])->toBe('7200.00')
        ->and($rural['invoices'])->toBe(0)
        ->and($rural['net'])->toBe('0.00')
        ->and($daily['totals'])->toBe(['invoices' => 1, 'sales' => '9000.00', 'returns' => '1800.00', 'net' => '7200.00']);
});

test('movements of a day are counted once per movement and type', function () {
    $today = today()->toDateString();
    ($this->buy)($this->city, $this->oil, '10', '10', $today);
    ($this->sell)($this->city, [['product_id' => $this->oil->id, 'quantity' => '1', 'unit_price' => '20']], $today);
    ($this->sell)($this->city, [['product_id' => $this->oil->id, 'quantity' => '1', 'unit_price' => '20']], $today);
    ($this->buy)($this->rural, $this->oil, '3', '10', $today);

    expect($this->reports->movementCounts($today))->toBe(['purchase' => 2, 'sale' => 2])
        ->and($this->reports->movementCounts($today, $this->rural->id))->toBe(['purchase' => 1]);
});

// --- Summary and details --------------------------------------------------------------------------------

test('summary groups net values by product, party or warehouse', function () {
    ($this->buy)($this->city, $this->oil, '100', '100', ($this->day)(9));
    ($this->buy)($this->city, $this->rice, '100', '100', ($this->day)(9));
    ($this->buy)($this->rural, $this->oil, '100', '100', ($this->day)(9));
    $anonymous = app(MovementService::class)->sale($this->owner, ['warehouse_id' => $this->rural->id, 'date' => ($this->day)(2), 'items' => [['product_id' => $this->oil->id, 'quantity' => '2', 'unit_price' => '300']]]);
    ($this->sell)($this->city, [
        ['product_id' => $this->oil->id, 'quantity' => '5', 'unit_price' => '300'],
        ['product_id' => $this->rice->id, 'quantity' => '5', 'unit_price' => '200'],
    ], ($this->day)(1), ['discount' => '250']);

    $filters = new ReportFilters(types: [MovementType::Sale]);

    $byProduct = $this->reports->summary($filters);
    $oil = $byProduct['rows']->firstWhere('label', 'زيت');
    expect($oil['qty_out'])->toBe('7.000')
        // 1,500 of the 2,500 city sale carries 1,500 × 2,250 ÷ 2,500 = 1,350; plus 600 from the cash sale
        ->and($oil['net_value'])->toBe('1950.00')
        ->and($byProduct['totals']['net_value'])->toBe('2850.00')
        ->and($byProduct['totals']['movements'])->toBe(2);

    $byParty = $this->reports->summary($filters->with(groupBy: 'party'));
    expect($byParty['rows']->pluck('net_value', 'label')->all())->toMatchArray(['سامر' => '2250.00'])
        ->and($byParty['rows'])->toHaveCount(2); // the customer and "no party"

    $byWarehouse = $this->reports->summary($filters->with(groupBy: 'warehouse'));
    expect($byWarehouse['rows']->pluck('net_value', 'label')->all())->toBe(['مستودع الريف' => '600.00', 'مستودع المدينة' => '2250.00']);

    expect($anonymous->total)->toBe('600.00');
});

test('details list the rows of the filter, oldest first, with their movement numbers', function () {
    ($this->buy)($this->city, $this->oil, '10', '10', ($this->day)(5));
    ($this->sell)($this->city, [['product_id' => $this->oil->id, 'quantity' => '3', 'unit_price' => '20']], ($this->day)(2));
    ($this->sell)($this->city, [['product_id' => $this->oil->id, 'quantity' => '1', 'unit_price' => '20']], ($this->day)(1));

    $all = collect($this->reports->details(new ReportFilters)->items());
    $sales = collect($this->reports->details(new ReportFilters(types: [MovementType::Sale], dateFrom: ($this->day)(2), dateTo: ($this->day)(1)))->items());

    expect($all->pluck('number')->all())->toBe(['P-0001', 'S-0001', 'S-0002'])
        ->and($sales->pluck('number')->all())->toBe(['S-0001', 'S-0002'])
        ->and($sales->first()['counterpart'])->toBe('سامر')
        ->and($sales->first()['qty_out'])->toBe('3.000')
        ->and($sales->first()['net_value'])->toBe('60.00');
});

test('a transfer is an out row in the source and an in row in the target', function () {
    $transfer = Movement::factory()->for($this->company)->create([
        'type' => MovementType::Transfer, 'number' => 1, 'warehouse_id' => $this->city->id, 'target_warehouse_id' => $this->rural->id,
        'created_by' => $this->city->id, 'date' => today()->toDateString(),
    ]);
    $item = MovementItem::factory()->create(['movement_id' => $transfer->id, 'product_id' => $this->oil->id, 'quantity' => '5.000', 'unit_price' => '10.00', 'line_total' => '50.00']);
    Operation::factory()->create(['movement_item_id' => $item->id, 'type' => MovementType::Transfer, 'qty_in' => '0.000', 'qty_out' => '5.000', 'value' => '50.00', 'net_value' => '50.00']);
    Operation::factory()->create(['movement_item_id' => $item->id, 'type' => MovementType::Transfer, 'warehouse_id' => $this->rural->id, 'qty_in' => '5.000', 'qty_out' => '0.000', 'value' => '50.00', 'net_value' => '50.00']);

    $rows = collect($this->reports->details(new ReportFilters(types: [MovementType::Transfer]))->items())->keyBy('warehouse_name');

    expect($rows)->toHaveCount(2)
        ->and($rows['مستودع المدينة']['qty_out'])->toBe('5.000')
        ->and($rows['مستودع المدينة']['counterpart'])->toBe('← مستودع الريف')
        ->and($rows['مستودع الريف']['qty_in'])->toBe('5.000')
        ->and($rows['مستودع الريف']['counterpart'])->toBe('من مستودع المدينة')
        // each warehouse sees only its own side
        ->and($this->reports->details(new ReportFilters(types: [MovementType::Transfer], warehouseId: $this->rural->id))->total())->toBe(1);
});

test('the party summary is invoices minus returns', function () {
    ($this->buy)($this->city, $this->oil, '100', '100', ($this->day)(8));
    $first = ($this->sell)($this->city, [['product_id' => $this->oil->id, 'quantity' => '10', 'unit_price' => '300']], ($this->day)(5));
    ($this->sell)($this->city, [['product_id' => $this->oil->id, 'quantity' => '5', 'unit_price' => '300']], ($this->day)(4));
    ($this->giveBack)($first, [['product_id' => $this->oil->id, 'quantity' => '2']], ($this->day)(3));

    $summary = $this->reports->partySummary(new ReportFilters(partyId: $this->customer->id));

    expect($summary)->toBe(['invoices_count' => 2, 'invoices_total' => '4500.00', 'returns_count' => 1, 'returns_total' => '600.00', 'net' => '3900.00']);
});

// --- Stock reports (SRS 3.6.6) ----------------------------------------------------------------------------

test('stock shows opening, in, out and remaining for the period', function () {
    ($this->buy)($this->city, $this->oil, '100', '10', ($this->day)(20));
    ($this->sell)($this->city, [['product_id' => $this->oil->id, 'quantity' => '30', 'unit_price' => '20']], ($this->day)(15));
    ($this->buy)($this->city, $this->oil, '5', '10', ($this->day)(4));
    ($this->sell)($this->city, [['product_id' => $this->oil->id, 'quantity' => '10', 'unit_price' => '20']], ($this->day)(2));

    $row = $this->reports->stock(new ReportFilters(productId: $this->oil->id, dateFrom: ($this->day)(10), dateTo: today()->toDateString()))->first();

    expect($row['opening'])->toBe('70.000')
        ->and($row['qty_in'])->toBe('5.000')
        ->and($row['qty_out'])->toBe('10.000')
        ->and($row['remaining'])->toBe('65.000')
        ->and($row['sold'])->toBe('10.000')
        ->and($row['last_sale'])->toBe(($this->day)(2))
        ->and($row['days_since_sale'])->toBe(2);

    // up to an earlier date
    $earlier = $this->reports->stock(new ReportFilters(productId: $this->oil->id, dateTo: ($this->day)(16)))->first();
    expect($earlier['remaining'])->toBe('100.000')->and($earlier['qty_out'])->toBe('0.000');
});

test('below_min counts a balance equal to the minimum and skips products without one', function () {
    ($this->buy)($this->city, $this->oil, '21', '10', ($this->day)(5));
    ($this->buy)($this->city, $this->rice, '50', '10', ($this->day)(5));
    ($this->buy)($this->city, $this->sugar, '1', '10', ($this->day)(5));
    ($this->sell)($this->city, [['product_id' => $this->oil->id, 'quantity' => '1', 'unit_price' => '20']], ($this->day)(1));
    $unbought = Product::factory()->for($this->company)->create(['name' => 'بدون حركة', 'min_stock' => '5']);
    Product::factory()->for($this->company)->inactive()->create(['name' => 'موقوفة']);

    $low = $this->reports->stock(new ReportFilters(stockFilter: StockFilter::BelowMin));

    // oil 20 ≤ 20, rice 50 ≤ 50; sugar has no minimum; the unbought one (minimum 5) was never stocked, so it is not "running low"
    expect($low->pluck('product.name')->sort()->values()->all())->toBe(['رز', 'زيت'])
        ->and($low->pluck('product.id')->all())->not->toContain($unbought->id);

    $all = $this->reports->stock(new ReportFilters);
    expect($all->pluck('product.name')->all())->not->toContain('موقوفة');
});

test('moved lists what was sold in the period, stagnant what has stock and sold nothing', function () {
    ($this->buy)($this->city, $this->oil, '100', '10', ($this->day)(40));
    ($this->buy)($this->city, $this->rice, '50', '10', ($this->day)(40));
    ($this->buy)($this->city, $this->sugar, '10', '10', ($this->day)(40));
    ($this->sell)($this->city, [['product_id' => $this->oil->id, 'quantity' => '4', 'unit_price' => '20']], ($this->day)(3));
    ($this->sell)($this->city, [['product_id' => $this->rice->id, 'quantity' => '50', 'unit_price' => '20']], ($this->day)(30)); // sold out long ago
    $period = new ReportFilters(dateFrom: ($this->day)(10), dateTo: today()->toDateString());

    $moved = $this->reports->stock($period->with(stockFilter: StockFilter::Moved));
    $stagnant = $this->reports->stock($period->with(stockFilter: StockFilter::Stagnant));

    expect($moved->pluck('product.name')->all())->toBe(['زيت'])
        ->and($moved->first()['sold'])->toBe('4.000')
        // rice has no stock left, so it is not stagnant; sugar never sold
        ->and($stagnant->pluck('product.name')->all())->toBe(['سكر'])
        ->and($stagnant->first()['last_sale'])->toBeNull();

    // a longer period makes oil's old purchase irrelevant but rice's sale count
    $wide = $this->reports->stock(new ReportFilters(dateFrom: ($this->day)(35), dateTo: today()->toDateString(), stockFilter: StockFilter::Moved));
    expect($wide->pluck('product.name')->sort()->values()->all())->toBe(['رز', 'زيت']);
});

test('stock can be split by warehouse and lists a warehouse with nothing as zero', function () {
    ($this->buy)($this->city, $this->oil, '10', '10', ($this->day)(5));
    ($this->buy)($this->rural, $this->oil, '30', '10', ($this->day)(5));

    $company = $this->reports->stock(new ReportFilters(productId: $this->oil->id));
    $split = $this->reports->stock(new ReportFilters(productId: $this->oil->id, splitByWarehouse: true));
    $low = $this->reports->stock(new ReportFilters(splitByWarehouse: true, stockFilter: StockFilter::BelowMin));

    expect($company->first()['remaining'])->toBe('40.000')
        ->and($split->mapWithKeys(fn ($r) => [$r['warehouse']->name => $r['remaining']])->all())->toBe(['مستودع الريف' => '30.000', 'مستودع المدينة' => '10.000'])
        // oil is below its minimum (20) only in the city warehouse; rice (minimum 50) never came in anywhere
        ->and($low->map(fn ($r) => $r['product']->name.'@'.$r['warehouse']->name)->all())->toBe(['زيت@مستودع المدينة']);
});

test('a product never received by a warehouse is not low there, but one that ran out is', function () {
    ($this->buy)($this->city, $this->oil, '30', '10', ($this->day)(5));
    ($this->buy)($this->rural, $this->rice, '60', '10', ($this->day)(5));
    ($this->sell)($this->rural, [['product_id' => $this->rice->id, 'quantity' => '60', 'unit_price' => '20']], ($this->day)(1));

    $split = $this->reports->stock(new ReportFilters(splitByWarehouse: true, stockFilter: StockFilter::BelowMin));
    $rural = $this->reports->stock(new ReportFilters(warehouseId: $this->rural->id, stockFilter: StockFilter::BelowMin));
    $inventory = $this->reports->stock(new ReportFilters(productId: $this->oil->id, splitByWarehouse: true));

    // rice ran out in the rural warehouse (history, balance 0 ≤ 50); oil is fine in the city and was never in the rural one
    expect($split->map(fn ($r) => $r['product']->name.'@'.$r['warehouse']->name)->all())->toBe(['رز@مستودع الريف'])
        ->and($rural->pluck('product.name')->all())->toBe(['رز'])
        // the full inventory sheet still lists every cell, zeros included
        ->and($inventory)->toHaveCount(2);
});

// --- Isolation --------------------------------------------------------------------------------------------

test('reports never show another company', function () {
    ($this->buy)($this->city, $this->oil, '10', '10', ($this->day)(1));

    $other = Company::factory()->create();
    $otherOwner = User::factory()->for($other)->create();
    $otherWarehouse = User::factory()->warehouse()->for($other)->create();
    $otherSupplier = Party::factory()->supplier()->for($other)->create();
    $otherProduct = Product::factory()->for($other)->create(['min_stock' => '5']);
    $this->actingAs($otherOwner);
    app(MovementService::class)->purchase($otherOwner, ['warehouse_id' => $otherWarehouse->id, 'party_id' => $otherSupplier->id, 'items' => [['product_id' => $otherProduct->id, 'quantity' => '9', 'unit_price' => '1']]]);

    $this->actingAs($this->owner);

    expect($this->reports->details(new ReportFilters)->total())->toBe(1)
        ->and($this->reports->summary(new ReportFilters)['rows'])->toHaveCount(1)
        ->and($this->reports->stock(new ReportFilters)->pluck('product.name')->all())->not->toContain($otherProduct->name);
});

test('a warehouse user sees only their own warehouse, whatever the filter says', function () {
    ($this->buy)($this->city, $this->oil, '10', '10', ($this->day)(1));
    ($this->buy)($this->rural, $this->oil, '3', '10', ($this->day)(1));

    $this->actingAs($this->rural);
    $filters = (new ReportFilters(warehouseId: $this->city->id))->forUser($this->rural);

    expect($filters->warehouseId)->toBe($this->rural->id)
        ->and($this->reports->details($filters)->total())->toBe(1)
        // even without the filter the scope holds
        ->and($this->reports->details(new ReportFilters)->total())->toBe(1)
        ->and($this->reports->stock(new ReportFilters(productId: $this->oil->id))->first()['remaining'])->toBe('3.000');
});
