<?php

use App\Models\Company;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Services\MovementService;
use App\Services\PricingService;
use App\Services\StockService;

beforeEach(function () {
    $this->company = Company::factory()->create(['markup_percent' => 200]);
    $this->owner = User::factory()->for($this->company)->create();
    $this->warehouse = User::factory()->warehouse()->for($this->company)->create();
    $this->otherWarehouse = User::factory()->warehouse()->for($this->company)->create();
    $this->supplier = Party::factory()->supplier()->for($this->company)->create();
    $this->product = Product::factory()->for($this->company)->create();

    $this->actingAs($this->owner);

    $this->buy = fn (string $quantity, string $price, string $date, ?User $warehouse = null) => app(MovementService::class)->purchase($this->owner, [
        'warehouse_id' => ($warehouse ?? $this->warehouse)->id,
        'party_id' => $this->supplier->id,
        'date' => $date,
        'items' => [['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => $price]],
    ]);
});

test('SRS 3.4.5: last purchase 1,000 at 200 percent suggests 2,000', function () {
    ($this->buy)('1', '1000', '2026-10-01');

    $pricing = app(PricingService::class);

    expect($pricing->lastPurchasePrice($this->product))->toBe('1000.00')
        ->and($pricing->suggestedPrice($this->product))->toBe('2000.00');
});

test('a manual sale price wins over the markup', function () {
    ($this->buy)('1', '1000', '2026-10-01');
    $this->product->update(['custom_sale_price' => '1750.50']);

    expect(app(PricingService::class)->suggestedPrice($this->product->fresh()))->toBe('1750.50');
});

test('a product that was never bought has no suggested price', function () {
    expect(app(PricingService::class)->lastPurchasePrice($this->product))->toBeNull()
        ->and(app(PricingService::class)->suggestedPrice($this->product))->toBeNull();
});

test('the last purchase is the latest by date, whatever the saving order', function () {
    ($this->buy)('1', '50', '2026-10-10');
    ($this->buy)('1', '40', '2026-10-05'); // saved later but dated earlier

    expect(app(PricingService::class)->lastPurchasePrice($this->product))->toBe('50.00');
});

test('the last purchase price is company wide, even for a warehouse user', function () {
    ($this->buy)('1', '70', '2026-10-10', $this->otherWarehouse);

    $this->actingAs($this->warehouse);

    expect(app(PricingService::class)->lastPurchasePrice($this->product))->toBe('70.00');
});

test('balances come from operations and can be taken as of a date', function () {
    ($this->buy)('10', '5', '2026-10-01');
    ($this->buy)('5', '5', '2026-10-20');
    ($this->buy)('99', '5', '2026-10-01', $this->otherWarehouse);

    $stock = app(StockService::class);

    expect($stock->balance($this->product->id, $this->warehouse->id))->toBe('15.000')
        ->and($stock->balance($this->product->id, $this->warehouse->id, Carbon\Carbon::parse('2026-10-10')))->toBe('10.000')
        ->and($stock->balance($this->product->id, $this->otherWarehouse->id))->toBe('99.000')
        ->and($stock->balance(Product::factory()->for($this->company)->create()->id, $this->warehouse->id))->toBe('0.000');
});
