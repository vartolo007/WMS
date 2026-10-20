<?php

use App\Enums\MovementType;
use App\Models\Company;
use App\Models\Movement;
use App\Models\MovementItem;
use App\Models\Operation;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Services\MovementService;
use App\Services\PostingService;
use App\Services\StockService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->company = Company::factory()->create(['markup_percent' => 200]);
    $this->owner = User::factory()->for($this->company)->create();
    $this->warehouse = User::factory()->warehouse()->for($this->company)->create();
    $this->supplier = Party::factory()->supplier()->for($this->company)->create();
    $this->customer = Party::factory()->for($this->company)->create();
    $this->productA = Product::factory()->for($this->company)->create(['name' => 'مادة أ']);
    $this->productB = Product::factory()->for($this->company)->create(['name' => 'مادة ب']);
    $this->productC = Product::factory()->for($this->company)->create(['name' => 'مادة ج']);

    $this->actingAs($this->owner);
    $this->service = app(MovementService::class);

    $this->purchase = fn (array $items, array $extra = []) => $this->service->purchase($this->owner, [
        'warehouse_id' => $this->warehouse->id,
        'party_id' => $this->supplier->id,
        'items' => $items,
        ...$extra,
    ]);

    $this->sale = fn (array $items, array $extra = []) => $this->service->sale($this->owner, [
        'warehouse_id' => $this->warehouse->id,
        'items' => $items,
        ...$extra,
    ]);

    $this->balance = fn (Product $product) => app(StockService::class)->balance($product->id, $this->warehouse->id);

    $this->errorsOf = function (Closure $action): array {
        try {
            $action();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    };
});

test('a purchase puts the goods into the warehouse', function () {
    $movement = ($this->purchase)([['product_id' => $this->productA->id, 'quantity' => '10', 'unit_price' => '5']]);

    expect($movement->type)->toBe(MovementType::Purchase)
        ->and($movement->number)->toBe(1)
        ->and($movement->total)->toBe('50.00')
        ->and(($this->balance)($this->productA))->toBe('10.000')
        ->and(Operation::count())->toBe(1);
});

test('a sale takes the goods out and is rejected beyond the stock', function () {
    ($this->purchase)([['product_id' => $this->productA->id, 'quantity' => '10', 'unit_price' => '5']]);

    ($this->sale)([['product_id' => $this->productA->id, 'quantity' => '4', 'unit_price' => '9']]);
    expect(($this->balance)($this->productA))->toBe('6.000');

    $errors = ($this->errorsOf)(fn () => ($this->sale)([['product_id' => $this->productA->id, 'quantity' => '6.001', 'unit_price' => '9']]));

    expect($errors)->toHaveKey('items.0.quantity')
        ->and(($this->balance)($this->productA))->toBe('6.000')
        ->and(Movement::where('type', MovementType::Sale)->count())->toBe(1);
});

test('a product repeated on an invoice is rejected, so a return can point at one original line', function () {
    $errors = ($this->errorsOf)(fn () => ($this->purchase)([
        ['product_id' => $this->productA->id, 'quantity' => '3', 'unit_price' => '9'],
        ['product_id' => $this->productA->id, 'quantity' => '3', 'unit_price' => '9'],
    ]));

    expect($errors)->toHaveKey('items.1.product_id')
        ->and(Movement::count())->toBe(0)
        ->and(($this->balance)($this->productA))->toBe('0.000');
});

test('SRS 6.2: a discount is shared between the lines and the nets add up to the total', function () {
    $movement = ($this->purchase)([
        ['product_id' => $this->productA->id, 'quantity' => '1', 'unit_price' => '6000'],
        ['product_id' => $this->productB->id, 'quantity' => '1', 'unit_price' => '4000'],
    ], ['discount' => '1000']);

    $nets = Operation::orderBy('id')->pluck('net_value')->all();

    expect($movement->subtotal)->toBe('10000.00')
        ->and($movement->discount)->toBe('1000.00')
        ->and($movement->total)->toBe('9000.00')
        ->and($nets)->toBe(['5400.00', '3600.00']);
});

test('the last line absorbs the rounding difference', function () {
    $movement = ($this->purchase)([
        ['product_id' => $this->productA->id, 'quantity' => '1', 'unit_price' => '3.33'],
        ['product_id' => $this->productB->id, 'quantity' => '1', 'unit_price' => '3.33'],
        ['product_id' => $this->productC->id, 'quantity' => '1', 'unit_price' => '3.34'],
    ], ['discount' => '0.01']);

    $nets = Operation::orderBy('id')->pluck('net_value')->all();

    expect($movement->total)->toBe('9.99')
        ->and($nets)->toBe(['3.33', '3.33', '3.33'])
        ->and(Operation::sum('net_value'))->toEqual(9.99);
});

test('net values sum exactly to the total for awkward ratios', function () {
    $movement = ($this->purchase)([
        ['product_id' => $this->productA->id, 'quantity' => '1', 'unit_price' => '33.33'],
        ['product_id' => $this->productB->id, 'quantity' => '1', 'unit_price' => '33.33'],
        ['product_id' => $this->productC->id, 'quantity' => '1', 'unit_price' => '33.34'],
    ], ['discount' => '10']);

    $sum = Operation::orderBy('id')->pluck('net_value')->reduce(fn ($carry, $v) => bcadd($carry, $v, 2), '0');

    expect($sum)->toBe($movement->total)->and($movement->total)->toBe('90.00');
});

test('with a zero subtotal each net value equals its value', function () {
    ($this->purchase)([['product_id' => $this->productA->id, 'quantity' => '3', 'unit_price' => '0']]);

    $operation = Operation::sole();

    expect($operation->value)->toBe('0.00')
        ->and($operation->net_value)->toBe('0.00')
        ->and($operation->qty_in)->toBe('3.000');
});

test('operations carry the movement data', function () {
    $movement = ($this->purchase)([['product_id' => $this->productA->id, 'quantity' => '2.5', 'unit_price' => '4']], ['date' => '2026-10-01']);

    $operation = Operation::sole();

    expect($operation->company_id)->toBe($this->company->id)
        ->and($operation->warehouse_id)->toBe($this->warehouse->id)
        ->and($operation->movement_id)->toBe($movement->id)
        ->and($operation->movement_item_id)->toBe($movement->items->first()->id)
        ->and($operation->party_id)->toBe($this->supplier->id)
        ->and($operation->type)->toBe(MovementType::Purchase)
        ->and($operation->date->toDateString())->toBe('2026-10-01')
        ->and($operation->qty_in)->toBe('2.500')
        ->and($operation->qty_out)->toBe('0.000')
        ->and($operation->unit_price)->toBe('4.00')
        ->and($operation->value)->toBe('10.00');
});

test('a sale uses the suggested price when none is sent, and a sent price wins', function () {
    ($this->purchase)([['product_id' => $this->productA->id, 'quantity' => '10', 'unit_price' => '1000']]);

    $suggested = ($this->sale)([['product_id' => $this->productA->id, 'quantity' => '1']]);
    $custom = ($this->sale)([['product_id' => $this->productA->id, 'quantity' => '1', 'unit_price' => '1500']]);

    expect($suggested->items->first()->unit_price)->toBe('2000.00')
        ->and($custom->items->first()->unit_price)->toBe('1500.00');
});

test('a sale of a product without any price is rejected', function () {
    // product B was never bought and has no manual price
    $errors = ($this->errorsOf)(fn () => ($this->sale)([['product_id' => $this->productB->id, 'quantity' => '1']]));

    expect($errors)->toHaveKey('items.0.unit_price');
});

test('a discount above the subtotal is rejected, equal to it is accepted', function () {
    $item = [['product_id' => $this->productA->id, 'quantity' => '1', 'unit_price' => '100']];

    $errors = ($this->errorsOf)(fn () => ($this->purchase)($item, ['discount' => '100.01']));
    expect($errors)->toHaveKey('discount')->and(Movement::count())->toBe(0);

    $movement = ($this->purchase)($item, ['discount' => '100']);
    expect($movement->total)->toBe('0.00');
});

test('a movement needs at least one line', function () {
    expect(($this->errorsOf)(fn () => ($this->purchase)([])))->toHaveKey('items');
});

test('party rules: a purchase needs a supplier, a sale takes a customer or none', function () {
    $item = [['product_id' => $this->productA->id, 'quantity' => '1', 'unit_price' => '5']];

    expect(($this->errorsOf)(fn () => ($this->purchase)($item, ['party_id' => null])))->toHaveKey('party_id')
        ->and(($this->errorsOf)(fn () => ($this->purchase)($item, ['party_id' => $this->customer->id])))->toHaveKey('party_id');

    ($this->purchase)([['product_id' => $this->productA->id, 'quantity' => '5', 'unit_price' => '5']]);

    $anonymous = ($this->sale)($item);
    $withCustomer = ($this->sale)($item, ['party_id' => $this->customer->id]);

    expect($anonymous->party_id)->toBeNull()
        ->and($withCustomer->party_id)->toBe($this->customer->id)
        ->and(($this->errorsOf)(fn () => ($this->sale)($item, ['party_id' => $this->supplier->id])))->toHaveKey('party_id');
});

test('inactive or foreign parties, products and warehouses are refused', function () {
    $item = [['product_id' => $this->productA->id, 'quantity' => '1', 'unit_price' => '5']];
    $otherCompany = Company::factory()->create();

    $inactiveSupplier = Party::factory()->supplier()->inactive()->for($this->company)->create();
    $foreignSupplier = Party::factory()->supplier()->for($otherCompany)->create();
    $inactiveProduct = Product::factory()->inactive()->for($this->company)->create();
    $foreignProduct = Product::factory()->for($otherCompany)->create();
    $inactiveWarehouse = User::factory()->warehouse()->inactive()->for($this->company)->create();
    $foreignWarehouse = User::factory()->warehouse()->for($otherCompany)->create();

    expect(($this->errorsOf)(fn () => ($this->purchase)($item, ['party_id' => $inactiveSupplier->id])))->toHaveKey('party_id')
        ->and(($this->errorsOf)(fn () => ($this->purchase)($item, ['party_id' => $foreignSupplier->id])))->toHaveKey('party_id')
        ->and(($this->errorsOf)(fn () => ($this->purchase)([['product_id' => $inactiveProduct->id, 'quantity' => '1', 'unit_price' => '5']])))->toHaveKey('items.0.product_id')
        ->and(($this->errorsOf)(fn () => ($this->purchase)([['product_id' => $foreignProduct->id, 'quantity' => '1', 'unit_price' => '5']])))->toHaveKey('items.0.product_id')
        ->and(($this->errorsOf)(fn () => ($this->purchase)($item, ['warehouse_id' => $inactiveWarehouse->id])))->toHaveKey('warehouse_id')
        ->and(($this->errorsOf)(fn () => ($this->purchase)($item, ['warehouse_id' => $foreignWarehouse->id])))->toHaveKey('warehouse_id')
        ->and(($this->errorsOf)(fn () => ($this->purchase)($item, ['warehouse_id' => $this->owner->id])))->toHaveKey('warehouse_id')
        ->and(Movement::count())->toBe(0);
});

test('numbers are sequential per company and type', function () {
    $item = [['product_id' => $this->productA->id, 'quantity' => '1', 'unit_price' => '5']];

    $p1 = ($this->purchase)($item);
    $p2 = ($this->purchase)($item);
    $s1 = ($this->sale)($item);

    $otherCompany = Company::factory()->create();
    $otherOwner = User::factory()->for($otherCompany)->create();
    $otherWarehouse = User::factory()->warehouse()->for($otherCompany)->create();
    $otherSupplier = Party::factory()->supplier()->for($otherCompany)->create();
    $otherProduct = Product::factory()->for($otherCompany)->create();
    $this->actingAs($otherOwner);
    $other = $this->service->purchase($otherOwner, [
        'warehouse_id' => $otherWarehouse->id,
        'party_id' => $otherSupplier->id,
        'items' => [['product_id' => $otherProduct->id, 'quantity' => '1', 'unit_price' => '5']],
    ]);

    expect([$p1->number, $p2->number, $s1->number, $other->number])->toBe([1, 2, 1, 1])
        ->and($p1->displayNumber())->toBe('P-0001');
});

test('everything is rolled back when posting fails', function () {
    $this->mock(PostingService::class)->shouldReceive('post')->andThrow(new RuntimeException('boom'));

    $service = app(MovementService::class);

    expect(fn () => $service->purchase($this->owner, [
        'warehouse_id' => $this->warehouse->id,
        'party_id' => $this->supplier->id,
        'items' => [['product_id' => $this->productA->id, 'quantity' => '1', 'unit_price' => '5']],
    ]))->toThrow(RuntimeException::class);

    expect(Movement::count())->toBe(0)
        ->and(MovementItem::count())->toBe(0)
        ->and(Operation::count())->toBe(0);
});

test('only PostingService writes to the operations table', function () {
    $forbidden = '/(new\s+Operation\b|Operation::(create|insert|upsert|query\(\)->(create|insert|delete)|forceCreate|firstOrCreate|updateOrCreate)|operations\(\)->(create|delete|insert)|table\([\'"]operations[\'"]\))/';

    $offenders = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())))
        ->filter(fn ($file) => $file->isFile() && $file->getExtension() === 'php')
        ->reject(fn ($file) => $file->getFilename() === 'PostingService.php')
        ->filter(fn ($file) => preg_match($forbidden, file_get_contents($file->getPathname())) === 1)
        ->map(fn ($file) => $file->getFilename())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
