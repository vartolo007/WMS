<?php

use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Models\Company;
use App\Models\Movement;
use App\Models\Operation;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Services\MovementService;
use App\Services\ReportService;
use App\Services\StockService;
use App\Support\ReportFilters;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->owner = User::factory()->for($this->company)->create();
    $this->city = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع المدينة']);
    $this->rural = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع الريف']);
    $this->third = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع ثالث']);
    $this->supplier = Party::factory()->supplier()->for($this->company)->create();
    $this->oil = Product::factory()->for($this->company)->create(['name' => 'زيت', 'unit' => 'liter']);
    $this->box = Product::factory()->for($this->company)->create(['name' => 'علب', 'unit' => 'box']);

    $this->actingAs($this->owner);
    $this->service = app(MovementService::class);

    $this->buy = fn (User $wh, Product $p, string $qty, string $price = '100') => $this->service->purchase($this->owner, [
        'warehouse_id' => $wh->id, 'party_id' => $this->supplier->id,
        'items' => [['product_id' => $p->id, 'quantity' => $qty, 'unit_price' => $price]],
    ]);
    $this->move = fn (User $from, ?User $to, array $items, array $extra = []) => $this->service->transfer($this->owner, [
        'warehouse_id' => $from->id, 'target_warehouse_id' => $to?->id, 'items' => $items, ...$extra,
    ]);
    $this->balance = fn (User $wh, ?Product $p = null) => app(StockService::class)->balances([($p ?? $this->oil)->id], $wh->id, unscoped: true)[($p ?? $this->oil)->id];

    $this->refused = function (Closure $action): array {
        try {
            $action();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    };

    ($this->buy)($this->city, $this->oil, '50', '120');
});

test('a transfer takes the goods out of the source and puts them into the target at once', function () {
    $transfer = ($this->move)($this->city, $this->rural, [['product_id' => $this->oil->id, 'quantity' => '12.5']]);

    expect($transfer->type)->toBe(MovementType::Transfer)
        ->and($transfer->displayNumber())->toBe('T-0001')
        ->and($transfer->party_id)->toBeNull()
        ->and($transfer->discount)->toBe('0.00')
        ->and($transfer->status)->toBe(MovementStatus::Confirmed)
        ->and(($this->balance)($this->city))->toBe('37.500')
        ->and(($this->balance)($this->rural))->toBe('12.500');

    $rows = Operation::withoutGlobalScopes()->where('movement_id', $transfer->id)->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->warehouse_id)->toBe($this->city->id)
        ->and($rows[0]->qty_out)->toBe('12.500')
        ->and($rows[1]->warehouse_id)->toBe($this->rural->id)
        ->and($rows[1]->qty_in)->toBe('12.500')
        ->and($rows[0]->net_value)->toBe($rows[1]->net_value);
});

test('the transfer is valued at the last purchase price, 0.00 when the product was never bought', function () {
    ($this->buy)($this->city, $this->box, '10', '75.5');
    ($this->buy)($this->city, $this->oil, '5', '130'); // the last purchase price of oil is now 130

    $transfer = ($this->move)($this->city, $this->rural, [
        ['product_id' => $this->oil->id, 'quantity' => '10'],
        ['product_id' => $this->box->id, 'quantity' => '2'],
    ]);

    expect($transfer->items->pluck('unit_price', 'product_id')->all())->toBe([$this->oil->id => '130.00', $this->box->id => '75.50'])
        ->and($transfer->subtotal)->toBe('1451.00')
        ->and($transfer->total)->toBe('1451.00');

    // typed prices do not count: the service ignores them
    $again = ($this->move)($this->city, $this->rural, [['product_id' => $this->oil->id, 'quantity' => '1', 'unit_price' => '9999']]);
    expect($again->items->first()->unit_price)->toBe('130.00');
});

test('a transfer beyond the source stock is refused and moves nothing', function () {
    $errors = ($this->refused)(fn () => ($this->move)($this->city, $this->rural, [['product_id' => $this->oil->id, 'quantity' => '50.001']]));

    expect($errors)->toHaveKey('items.0.quantity')
        ->and(Movement::where('type', MovementType::Transfer)->count())->toBe(0)
        ->and(($this->balance)($this->city))->toBe('50.000')
        ->and(($this->balance)($this->rural))->toBe('0.000');
});

test('the target must be another active warehouse of the same company', function () {
    $item = [['product_id' => $this->oil->id, 'quantity' => '1']];
    $stopped = User::factory()->warehouse()->inactive()->for($this->company)->create();
    $foreign = User::factory()->warehouse()->for(Company::factory()->create())->create();

    expect(($this->refused)(fn () => ($this->move)($this->city, null, $item)))->toHaveKey('target_warehouse_id')
        ->and(($this->refused)(fn () => ($this->move)($this->city, $this->city, $item)))->toHaveKey('target_warehouse_id')
        ->and(($this->refused)(fn () => ($this->move)($this->city, $stopped, $item)))->toHaveKey('target_warehouse_id')
        ->and(($this->refused)(fn () => ($this->move)($this->city, $foreign, $item)))->toHaveKey('target_warehouse_id')
        ->and(($this->refused)(fn () => ($this->move)($this->city, $this->owner, $item)))->toHaveKey('target_warehouse_id')
        ->and(Movement::where('type', MovementType::Transfer)->count())->toBe(0);
});

test('a transfer needs lines, one per product, of active products', function () {
    $stopped = Product::factory()->inactive()->for($this->company)->create();

    expect(($this->refused)(fn () => ($this->move)($this->city, $this->rural, [])))->toHaveKey('items')
        ->and(($this->refused)(fn () => ($this->move)($this->city, $this->rural, [
            ['product_id' => $this->oil->id, 'quantity' => '1'],
            ['product_id' => $this->oil->id, 'quantity' => '1'],
        ])))->toHaveKey('items.1.product_id')
        ->and(($this->refused)(fn () => ($this->move)($this->city, $this->rural, [['product_id' => $stopped->id, 'quantity' => '1']])))->toHaveKey('items.0.product_id');
});

test('the receiving warehouse sees the transfer and its own row; a third warehouse sees nothing', function () {
    $transfer = ($this->move)($this->city, $this->rural, [['product_id' => $this->oil->id, 'quantity' => '5']]);

    $this->actingAs($this->rural);
    expect(Movement::find($transfer->id))->not->toBeNull()
        ->and(Operation::where('movement_id', $transfer->id)->pluck('warehouse_id')->all())->toBe([$this->rural->id])
        ->and(app(StockService::class)->balance($this->oil->id, $this->rural->id))->toBe('5.000');

    $this->actingAs($this->third);
    expect(Movement::find($transfer->id))->toBeNull()
        ->and(Operation::where('movement_id', $transfer->id)->count())->toBe(0);
});

test('the transfer shows in the reports as out in the source and in at the target', function () {
    ($this->move)($this->city, $this->rural, [['product_id' => $this->oil->id, 'quantity' => '5']]);
    $reports = app(ReportService::class);

    $rows = collect($reports->details(new ReportFilters(types: [MovementType::Transfer]))->items())->keyBy('warehouse_name');
    $ledgerRural = $reports->ledger(new ReportFilters(productId: $this->oil->id, warehouseId: $this->rural->id));

    expect($rows['مستودع المدينة']['qty_out'])->toBe('5.000')
        ->and($rows['مستودع المدينة']['counterpart'])->toBe('← مستودع الريف')
        ->and($rows['مستودع الريف']['qty_in'])->toBe('5.000')
        ->and($rows['مستودع الريف']['counterpart'])->toBe('من مستودع المدينة')
        ->and($ledgerRural['closing'])->toBe('5.000')
        // the company total does not change
        ->and($reports->stock(new ReportFilters(productId: $this->oil->id))->first()['remaining'])->toBe('50.000');
});

test('numbers are sequential per type and a transfer can be cancelled', function () {
    $first = ($this->move)($this->city, $this->rural, [['product_id' => $this->oil->id, 'quantity' => '5']]);
    $second = ($this->move)($this->city, $this->third, [['product_id' => $this->oil->id, 'quantity' => '5']]);

    expect([$first->number, $second->number])->toBe([1, 2]);

    $this->service->cancel($this->owner, $first);

    expect(($this->balance)($this->city))->toBe('45.000')
        ->and(($this->balance)($this->rural))->toBe('0.000')
        ->and(Operation::withoutGlobalScopes()->where('movement_id', $first->id)->count())->toBe(0);
});

test('saving a transfer over HTTP: the warehouse user sends from their own warehouse', function () {
    $payload = ['type' => 'transfer', 'warehouse_id' => $this->rural->id, 'target_warehouse_id' => $this->third->id, 'items' => [['product_id' => $this->oil->id, 'quantity' => '4']]];

    $this->actingAs($this->city);
    $response = $this->postJson('/movements', $payload)->assertOk();

    $transfer = Movement::where('type', MovementType::Transfer)->sole();
    $response->assertJson(['redirect' => route('movements.show', $transfer)]);
    expect($transfer->warehouse_id)->toBe($this->city->id)
        ->and($transfer->target_warehouse_id)->toBe($this->third->id);
});

test('the transfer request rejects what a transfer cannot have', function () {
    $item = ['product_id' => $this->oil->id, 'quantity' => '4'];
    $base = ['type' => 'transfer', 'warehouse_id' => $this->city->id, 'target_warehouse_id' => $this->rural->id, 'items' => [$item]];
    $customer = Party::factory()->for($this->company)->create();

    $this->postJson('/movements', [...$base, 'target_warehouse_id' => null])->assertJsonValidationErrors(['target_warehouse_id']);
    $this->postJson('/movements', [...$base, 'party_id' => $customer->id])->assertJsonValidationErrors(['party_id']);
    $this->postJson('/movements', [...$base, 'discount' => '5'])->assertJsonValidationErrors(['discount']);
    $this->postJson('/movements', [...$base, 'document_no' => 'X1'])->assertJsonValidationErrors(['document_no']);
    $this->postJson('/movements', [...$base, 'items' => [[...$item, 'unit_price' => '3']]])->assertJsonValidationErrors(['items.0.unit_price']);
    $this->postJson('/movements', [...$base, 'target_warehouse_id' => $this->city->id])->assertJsonValidationErrors(['target_warehouse_id']);

    // an invoice cannot name a target warehouse
    $this->postJson('/movements', ['type' => 'sale', 'warehouse_id' => $this->city->id, 'target_warehouse_id' => $this->rural->id, 'items' => [$item]])->assertJsonValidationErrors(['target_warehouse_id']);

    // the JSON the screen sends has null for the fields a transfer does not use
    $this->postJson('/movements', [...$base, 'party_id' => null, 'discount' => null, 'document_no' => null, 'items' => [[...$item, 'unit_price' => null]]])->assertOk();
});
