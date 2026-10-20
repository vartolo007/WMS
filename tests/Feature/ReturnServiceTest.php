<?php

use App\Enums\MovementStatus;
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
use App\Services\PricingService;
use App\Services\ReturnService;
use App\Services\StockService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->company = Company::factory()->create(['markup_percent' => 200]);
    $this->owner = User::factory()->for($this->company)->create();
    $this->warehouse = User::factory()->warehouse()->for($this->company)->create();
    $this->supplier = Party::factory()->supplier()->for($this->company)->create();
    $this->customer = Party::factory()->for($this->company)->create();
    $this->oil = Product::factory()->for($this->company)->create(['name' => 'زيت', 'unit' => 'liter']);
    $this->rice = Product::factory()->for($this->company)->create(['name' => 'رز', 'unit' => 'kilo']);

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
        'party_id' => $this->customer->id,
        'items' => $items,
        ...$extra,
    ]);

    $this->giveBack = fn (Movement $original, array $items, array $extra = []) => $this->service->return($this->owner, $original, ['items' => $items, ...$extra]);

    $this->balance = fn (Product $product) => app(StockService::class)->balance($product->id, $this->warehouse->id);

    $this->errorsOf = function (Closure $action): array {
        try {
            $action();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    };

    // 10,000 stock of everything so sales are never the limiting factor
    ($this->purchase)([
        ['product_id' => $this->oil->id, 'quantity' => '100', 'unit_price' => '1000'],
        ['product_id' => $this->rice->id, 'quantity' => '100', 'unit_price' => '1000'],
    ]);
});

test('SRS 6.2: a return carries its proportional share of the invoice discount (2,000 becomes 1,800)', function () {
    // 6,000 + 4,000 = 10,000, discount 1,000 → paid 9,000
    $sale = ($this->sale)([
        ['product_id' => $this->oil->id, 'quantity' => '6', 'unit_price' => '1000'],
        ['product_id' => $this->rice->id, 'quantity' => '4', 'unit_price' => '1000'],
    ], ['discount' => '1000']);

    $return = ($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '2']]);

    expect($return->type)->toBe(MovementType::SaleReturn)
        ->and($return->subtotal)->toBe('2000.00')
        ->and($return->total)->toBe('1800.00')
        ->and($return->discount)->toBe('200.00')
        ->and($return->related_movement_id)->toBe($sale->id)
        ->and($return->party_id)->toBe($this->customer->id)
        ->and($return->warehouse_id)->toBe($this->warehouse->id)
        ->and($return->items->first()->unit_price)->toBe('1000.00');
});

test('a sale return puts the goods back and posts one row per line that adds up to the total', function () {
    $sale = ($this->sale)([
        ['product_id' => $this->oil->id, 'quantity' => '10', 'unit_price' => '1000'],
        ['product_id' => $this->rice->id, 'quantity' => '10', 'unit_price' => '3'],
    ], ['discount' => '1000.01']);
    $before = ($this->balance)($this->oil);

    $return = ($this->giveBack)($sale, [
        ['product_id' => $this->oil->id, 'quantity' => '3'],
        ['product_id' => $this->rice->id, 'quantity' => '7'],
    ]);

    $rows = Operation::where('movement_id', $return->id)->orderBy('id')->get();

    expect(($this->balance)($this->oil))->toBe(bcadd($before, '3.000', 3))
        ->and($rows)->toHaveCount(2)
        ->and($rows->pluck('qty_in')->all())->toBe(['3.000', '7.000'])
        ->and($rows->pluck('qty_out')->unique()->all())->toBe(['0.000'])
        ->and($rows->first()->type)->toBe(MovementType::SaleReturn)
        ->and($rows->first()->party_id)->toBe($this->customer->id)
        ->and(bcadd((string) $rows->first()->net_value, (string) $rows->last()->net_value, 2))->toBe($return->total);
});

test('a purchase return takes goods out and is refused beyond the current stock', function () {
    $purchase = ($this->purchase)([['product_id' => $this->oil->id, 'quantity' => '10', 'unit_price' => '500']]);
    ($this->sale)([['product_id' => $this->oil->id, 'quantity' => '105', 'unit_price' => '2000']]);
    // stock is now 5 of the 110 bought; 10 were bought on this invoice

    $errors = ($this->errorsOf)(fn () => ($this->giveBack)($purchase, [['product_id' => $this->oil->id, 'quantity' => '6']]));
    expect($errors)->toHaveKey('items.0.quantity')
        ->and(($this->balance)($this->oil))->toBe('5.000');

    $return = ($this->giveBack)($purchase, [['product_id' => $this->oil->id, 'quantity' => '5']]);

    expect($return->type)->toBe(MovementType::PurchaseReturn)
        ->and($return->party_id)->toBe($this->supplier->id)
        ->and($return->total)->toBe('2500.00')
        ->and(($this->balance)($this->oil))->toBe('0.000')
        ->and(Operation::where('movement_id', $return->id)->sole()->qty_out)->toBe('5.000');
});

test('the returnable quantity is the invoice quantity minus the confirmed returns, summed over all returns', function () {
    $sale = ($this->sale)([['product_id' => $this->oil->id, 'quantity' => '10', 'unit_price' => '1000']]);

    ($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '4']]);
    ($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '5.5']]);

    $line = app(ReturnService::class)->lines($sale)->get($this->oil->id);
    expect($line['returned'])->toBe('9.500')->and($line['returnable'])->toBe('0.500');

    $errors = ($this->errorsOf)(fn () => ($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '0.501']]));
    expect($errors)->toHaveKey('items.0.quantity');

    ($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '0.5']]);
    expect(app(ReturnService::class)->isReturnable($sale))->toBeFalse();
});

test('a cancelled return gives its quantity back to the invoice', function () {
    $sale = ($this->sale)([['product_id' => $this->oil->id, 'quantity' => '10', 'unit_price' => '1000']]);
    $return = ($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '10']]);

    expect(app(ReturnService::class)->isReturnable($sale))->toBeFalse();

    $return->update(['status' => MovementStatus::Cancelled]);

    expect(app(ReturnService::class)->lines($sale)->get($this->oil->id)['returnable'])->toBe('10.000');
});

test('a product that is not on the original invoice cannot be returned', function () {
    $sale = ($this->sale)([['product_id' => $this->oil->id, 'quantity' => '1', 'unit_price' => '1000']]);

    $errors = ($this->errorsOf)(fn () => ($this->giveBack)($sale, [['product_id' => $this->rice->id, 'quantity' => '1']]));

    expect($errors)->toHaveKey('items.0.product_id')
        ->and(Movement::where('type', MovementType::SaleReturn)->count())->toBe(0);
});

test('only a confirmed purchase or sale can be returned', function () {
    $sale = ($this->sale)([['product_id' => $this->oil->id, 'quantity' => '2', 'unit_price' => '1000']]);
    $return = ($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '1']]);

    // a return cannot be returned
    expect(($this->errorsOf)(fn () => ($this->giveBack)($return, [['product_id' => $this->oil->id, 'quantity' => '1']])))->toHaveKey('original');

    // a cancelled invoice cannot be returned
    $sale->update(['status' => MovementStatus::Cancelled]);
    expect(($this->errorsOf)(fn () => ($this->giveBack)($sale->fresh(), [['product_id' => $this->oil->id, 'quantity' => '1']])))->toHaveKey('original')
        ->and(app(ReturnService::class)->isReturnable($sale->fresh()))->toBeFalse();
});

test('a return needs at least one line and one line per product', function () {
    $sale = ($this->sale)([['product_id' => $this->oil->id, 'quantity' => '5', 'unit_price' => '1000']]);

    expect(($this->errorsOf)(fn () => ($this->giveBack)($sale, [])))->toHaveKey('items')
        ->and(($this->errorsOf)(fn () => ($this->giveBack)($sale, [
            ['product_id' => $this->oil->id, 'quantity' => '2'],
            ['product_id' => $this->oil->id, 'quantity' => '2'],
        ])))->toHaveKey('items.1.product_id');
});

test('stopped warehouses, parties and products take no new returns (SRS 7)', function () {
    $sale = ($this->sale)([['product_id' => $this->oil->id, 'quantity' => '5', 'unit_price' => '1000']]);

    $this->oil->update(['is_active' => false]);
    expect(($this->errorsOf)(fn () => ($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '1']])))->toHaveKey('items.0.product_id');
    $this->oil->update(['is_active' => true]);

    $this->customer->update(['is_active' => false]);
    expect(($this->errorsOf)(fn () => ($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '1']])))->toHaveKey('party_id');
    $this->customer->update(['is_active' => true]);

    $this->warehouse->update(['is_active' => false]);
    expect(($this->errorsOf)(fn () => ($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '1']])))->toHaveKey('warehouse_id');
});

test('an invoice with a zero subtotal gives a zero return', function () {
    $sale = ($this->sale)([['product_id' => $this->oil->id, 'quantity' => '3', 'unit_price' => '0']]);

    $return = ($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '2']]);

    expect($return->subtotal)->toBe('0.00')->and($return->discount)->toBe('0.00')->and($return->total)->toBe('0.00');
});

test('a sale without a customer gives a return without a party', function () {
    $sale = $this->service->sale($this->owner, [
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_id' => $this->oil->id, 'quantity' => '1', 'unit_price' => '1000']],
    ]);

    expect(($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '1']])->party_id)->toBeNull();
});

test('return numbers are sequential per type, apart from invoices', function () {
    $sale = ($this->sale)([['product_id' => $this->oil->id, 'quantity' => '5', 'unit_price' => '1000']]);

    $first = ($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '1']]);
    $second = ($this->giveBack)($sale, [['product_id' => $this->oil->id, 'quantity' => '1']]);

    expect([$first->number, $second->number])->toBe([1, 2])
        ->and($first->displayNumber())->toBe('SR-0001')
        ->and($sale->number)->toBe(1);
});

test('a purchase return does not change the last purchase price', function () {
    $purchase = ($this->purchase)([['product_id' => $this->rice->id, 'quantity' => '10', 'unit_price' => '777']], ['date' => today()->addDay()->toDateString()]);

    ($this->giveBack)($purchase, [['product_id' => $this->rice->id, 'quantity' => '2']], ['date' => today()->addDays(5)->toDateString()]);

    expect(app(PricingService::class)->lastPurchasePrice($this->rice))->toBe('777.00');
});

test('everything is rolled back when posting a return fails', function () {
    $sale = ($this->sale)([['product_id' => $this->oil->id, 'quantity' => '5', 'unit_price' => '1000']]);
    $this->mock(PostingService::class)->shouldReceive('post')->andThrow(new RuntimeException('boom'));

    expect(fn () => app(MovementService::class)->return($this->owner, $sale, ['items' => [['product_id' => $this->oil->id, 'quantity' => '1']]]))
        ->toThrow(RuntimeException::class);

    expect(Movement::where('type', MovementType::SaleReturn)->count())->toBe(0)
        ->and(MovementItem::whereHas('movement', fn ($q) => $q->where('type', MovementType::SaleReturn))->count())->toBe(0);
});

test('the movement of another company cannot be returned', function () {
    $sale = ($this->sale)([['product_id' => $this->oil->id, 'quantity' => '1', 'unit_price' => '1000']]);
    $otherOwner = User::factory()->for(Company::factory()->create())->create();

    $this->actingAs($otherOwner);

    expect(($this->errorsOf)(fn () => app(MovementService::class)->return($otherOwner, $sale, ['items' => [['product_id' => $this->oil->id, 'quantity' => '1']]])))->toHaveKey('original');
});
