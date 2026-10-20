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
use App\Services\PricingService;
use App\Services\ReportService;
use App\Services\ReturnService;
use App\Services\StockService;
use App\Support\ReportFilters;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->owner = User::factory()->for($this->company)->create();
    $this->city = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع المدينة']);
    $this->rural = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع الريف']);
    $this->supplier = Party::factory()->supplier()->for($this->company)->create();
    $this->customer = Party::factory()->for($this->company)->create();
    $this->oil = Product::factory()->for($this->company)->create(['name' => 'زيت', 'unit' => 'liter']);

    $this->actingAs($this->owner);
    $this->service = app(MovementService::class);

    $this->buy = fn (User $wh, string $qty, string $price = '100', ?string $date = null) => $this->service->purchase($this->owner, [
        'warehouse_id' => $wh->id, 'party_id' => $this->supplier->id, 'date' => $date ?? today()->toDateString(),
        'items' => [['product_id' => $this->oil->id, 'quantity' => $qty, 'unit_price' => $price]],
    ]);
    $this->sell = fn (User $wh, string $qty) => $this->service->sale($this->owner, [
        'warehouse_id' => $wh->id, 'party_id' => $this->customer->id,
        'items' => [['product_id' => $this->oil->id, 'quantity' => $qty, 'unit_price' => '300']],
    ]);
    $this->giveBack = fn (Movement $original, string $qty) => $this->service->return($this->owner, $original, ['items' => [['product_id' => $this->oil->id, 'quantity' => $qty]]]);

    $this->balance = fn (User $wh) => app(StockService::class)->balances([$this->oil->id], $wh->id, unscoped: true)[$this->oil->id];

    $this->refused = function (Closure $action): ?string {
        try {
            $action();
        } catch (ValidationException $e) {
            return $e->errors()['cancel'][0] ?? 'other';
        }

        return null;
    };

    // a transfer built by hand: the transfer service is tested separately
    $this->transfer = function (User $from, User $to, string $qty) {
        $movement = Movement::factory()->for($this->company)->create([
            'type' => MovementType::Transfer, 'number' => (Movement::withoutGlobalScopes()->where('type', MovementType::Transfer)->max('number') ?? 0) + 1,
            'warehouse_id' => $from->id, 'target_warehouse_id' => $to->id, 'created_by' => $from->id, 'date' => today()->toDateString(),
        ]);
        $item = MovementItem::factory()->create(['movement_id' => $movement->id, 'product_id' => $this->oil->id, 'quantity' => $qty, 'unit_price' => '100.00', 'line_total' => '0.00']);
        Operation::factory()->create(['movement_item_id' => $item->id, 'type' => MovementType::Transfer, 'qty_in' => '0.000', 'qty_out' => $qty]);
        Operation::factory()->create(['movement_item_id' => $item->id, 'type' => MovementType::Transfer, 'warehouse_id' => $to->id, 'qty_in' => $qty, 'qty_out' => '0.000']);

        return $movement;
    };
});

test('cancelling a purchase removes its operations and keeps the movement with who and when', function () {
    $purchase = ($this->buy)($this->city, '10');

    $cancelled = $this->service->cancel($this->owner, $purchase);

    expect($cancelled->status)->toBe(MovementStatus::Cancelled)
        ->and($cancelled->cancelled_by)->toBe($this->owner->id)
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and(Operation::where('movement_id', $purchase->id)->count())->toBe(0)
        ->and(MovementItem::where('movement_id', $purchase->id)->count())->toBe(1)
        ->and(($this->balance)($this->city))->toBe('0.000')
        ->and($cancelled->fresh()->canceller->is($this->owner))->toBeTrue();
});

test('a purchase cannot be cancelled once its goods were sold, but can while the stock remains', function () {
    $purchase = ($this->buy)($this->city, '10');
    ($this->sell)($this->city, '4');

    $reason = ($this->refused)(fn () => $this->service->cancel($this->owner, $purchase));

    expect($reason)->toContain('سالب')
        ->and($reason)->toContain('زيت')
        ->and($reason)->toContain('مستودع المدينة')
        ->and($purchase->fresh()->status)->toBe(MovementStatus::Confirmed)
        ->and(Operation::where('movement_id', $purchase->id)->count())->toBe(1)
        ->and(($this->balance)($this->city))->toBe('6.000');

    // 10 + 10 bought, 10 sold: cancelling one purchase leaves exactly 0, which is allowed
    $second = ($this->buy)($this->city, '10');
    ($this->sell)($this->city, '6');
    expect(($this->refused)(fn () => $this->service->cancel($this->owner, $second)))->toBeNull()
        ->and(($this->balance)($this->city))->toBe('0.000');
});

test('cancelling a sale or a purchase return gives the stock back without any check', function () {
    $purchase = ($this->buy)($this->city, '10');
    $sale = ($this->sell)($this->city, '10');
    expect(($this->balance)($this->city))->toBe('0.000');

    $this->service->cancel($this->owner, $sale);
    expect(($this->balance)($this->city))->toBe('10.000');

    $return = ($this->giveBack)($purchase, '3');
    expect(($this->balance)($this->city))->toBe('7.000');

    $this->service->cancel($this->owner, $return);
    expect(($this->balance)($this->city))->toBe('10.000');
});

test('a sale return cannot be cancelled when its goods already left', function () {
    ($this->buy)($this->city, '100');
    $sale = ($this->sell)($this->city, '6');
    $return = ($this->giveBack)($sale, '2');
    ($this->sell)($this->city, '96'); // everything is gone again

    expect(($this->refused)(fn () => $this->service->cancel($this->owner, $return)))->toContain('سالب')
        ->and($return->fresh()->status)->toBe(MovementStatus::Confirmed);
});

test('an invoice with an active return must wait until the return is cancelled', function () {
    ($this->buy)($this->city, '100');
    $sale = ($this->sell)($this->city, '10');
    $return = ($this->giveBack)($sale, '4');

    expect(($this->refused)(fn () => $this->service->cancel($this->owner, $sale)))->toContain('مرتجع فعّال');

    $this->service->cancel($this->owner, $return);

    // the returnable quantity grows back, then the invoice itself can go
    expect(app(ReturnService::class)->lines($sale)->get($this->oil->id)['returnable'])->toBe('10.000')
        ->and(($this->refused)(fn () => $this->service->cancel($this->owner, $sale)))->toBeNull();
});

test('a movement cannot be cancelled twice, and a cancelled invoice cannot be returned', function () {
    $purchase = ($this->buy)($this->city, '10');
    $sale = ($this->sell)($this->city, '2');

    $this->service->cancel($this->owner, $sale);

    expect(($this->refused)(fn () => $this->service->cancel($this->owner, $sale)))->toContain('ملغاة')
        ->and(fn () => ($this->giveBack)($sale->fresh(), '1'))->toThrow(ValidationException::class)
        ->and($purchase->fresh()->status)->toBe(MovementStatus::Confirmed);
});

test('reports and the last purchase price ignore a cancelled purchase', function () {
    ($this->buy)($this->city, '10', '100', today()->subDays(3)->toDateString());
    $newer = ($this->buy)($this->city, '10', '250', today()->subDay()->toDateString());
    $pricing = app(PricingService::class);

    expect($pricing->lastPurchasePrice($this->oil))->toBe('250.00');

    $this->service->cancel($this->owner, $newer);

    expect($pricing->lastPurchasePrice($this->oil))->toBe('100.00')
        ->and(app(ReportService::class)->details(new ReportFilters)->total())->toBe(1)
        ->and(app(ReportService::class)->stock(new ReportFilters(productId: $this->oil->id))->first()['remaining'])->toBe('10.000');
});

test('cancelling a transfer removes both rows and needs the stock to still be at the target', function () {
    ($this->buy)($this->city, '20');
    $transfer = ($this->transfer)($this->city, $this->rural, '5');
    expect(($this->balance)($this->city))->toBe('15.000')->and(($this->balance)($this->rural))->toBe('5.000');

    $sale = ($this->sell)($this->rural, '4'); // the target already sold most of it
    expect(($this->refused)(fn () => $this->service->cancel($this->owner, $transfer)))->toContain('مستودع الريف')
        ->and(Operation::withoutGlobalScopes()->where('movement_id', $transfer->id)->count())->toBe(2);

    $this->service->cancel($this->owner, $sale);
    $this->service->cancel($this->owner, $transfer);

    expect(Operation::withoutGlobalScopes()->where('movement_id', $transfer->id)->count())->toBe(0)
        ->and(($this->balance)($this->city))->toBe('20.000')
        ->and(($this->balance)($this->rural))->toBe('0.000');
});

test('the sending warehouse removes the receiving side too when it cancels a transfer', function () {
    ($this->buy)($this->city, '20');
    $transfer = ($this->transfer)($this->city, $this->rural, '5');

    $this->actingAs($this->city);
    $this->service->cancel($this->city, Movement::findOrFail($transfer->id));

    expect(Operation::withoutGlobalScopes()->where('movement_id', $transfer->id)->count())->toBe(0)
        ->and($transfer->fresh()->cancelled_by)->toBe($this->city->id);
});

test('who may cancel: the owner and the owning warehouse, only while confirmed', function () {
    ($this->buy)($this->city, '20');
    $purchase = ($this->buy)($this->city, '5');
    $transfer = ($this->transfer)($this->city, $this->rural, '3');

    expect(Gate::forUser($this->owner)->allows('cancel', $purchase))->toBeTrue()
        ->and(Gate::forUser($this->city)->allows('cancel', $purchase))->toBeTrue()
        ->and(Gate::forUser($this->rural)->allows('cancel', $purchase))->toBeFalse()
        // the receiving warehouse may view the transfer but not cancel it
        ->and(Gate::forUser($this->rural)->allows('view', $transfer))->toBeTrue()
        ->and(Gate::forUser($this->rural)->allows('cancel', $transfer))->toBeFalse()
        ->and(Gate::forUser($this->city)->allows('cancel', $transfer))->toBeTrue()
        ->and(Gate::forUser(User::factory()->for(Company::factory()->create())->create())->allows('cancel', $purchase))->toBeFalse();

    $this->service->cancel($this->owner, $purchase);

    expect(Gate::forUser($this->owner)->allows('cancel', $purchase->fresh()))->toBeFalse();
});

test('a movement of another company cannot be cancelled', function () {
    $purchase = ($this->buy)($this->city, '5');
    $other = User::factory()->for(Company::factory()->create())->create();
    $this->actingAs($other);

    expect(($this->refused)(fn () => $this->service->cancel($other, $purchase)))->not->toBeNull()
        ->and($purchase->fresh()->status)->toBe(MovementStatus::Confirmed);
});
