<?php

use App\Models\Company;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Services\MovementService;
use App\Services\PricingService;

beforeEach(function () {
    $this->company = Company::factory()->create(['markup_percent' => 200, 'currency_symbol' => 'ل.س']);
    $this->owner = User::factory()->for($this->company)->create();
    $this->city = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع المدينة']);
    $this->rural = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع الريف']);
    $this->supplier = Party::factory()->supplier()->for($this->company)->create();
    $this->oil = Product::factory()->for($this->company)->create(['name' => 'زيت زيتون', 'unit' => 'liter']);
    $this->boxes = Product::factory()->for($this->company)->create(['name' => 'معلبات فول', 'unit' => 'box']);

    $this->buy = function (User $warehouse, Product $product, string $quantity, string $price, string $date = '2026-10-01') {
        $this->actingAs($this->owner);

        return app(MovementService::class)->purchase($this->owner, [
            'warehouse_id' => $warehouse->id,
            'party_id' => $this->supplier->id,
            'date' => $date,
            'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $price]],
        ]);
    };
});

// --- Products list: balance and suggested price (screen 11) ----------------------------------------

test('the owner sees the company balance, or the balance of the warehouse chosen in the top bar', function () {
    ($this->buy)($this->city, $this->oil, '40', '100');
    ($this->buy)($this->rural, $this->oil, '10.5', '100');
    ($this->buy)($this->rural, $this->boxes, '7', '50');

    $this->actingAs($this->owner);

    $this->get('/products')->assertOk()->assertSee('كل المستودعات')->assertSee('50.500')->assertSeeInOrder(['معلبات فول', '7']);

    $this->withSession(['warehouse_id' => $this->city->id])->get('/products')
        ->assertSee('مستودع المدينة')
        ->assertSee('40.000')
        ->assertDontSee('50.500');
});

test('a warehouse user sees only the balance of their own warehouse', function () {
    ($this->buy)($this->city, $this->oil, '40', '100');
    ($this->buy)($this->rural, $this->oil, '10', '100');

    $this->actingAs($this->rural)->get('/products')->assertOk()->assertSee('10.000')->assertDontSee('40.000')->assertDontSee('50.000');
});

test('a product that was never bought shows a zero balance and no suggested price', function () {
    $this->actingAs($this->owner);

    $response = $this->get('/products')->assertOk();

    expect($response->viewData('balances'))->toEqualCanonicalizing([$this->boxes->id => '0.000', $this->oil->id => '0.000'])
        ->and($response->viewData('suggestedPrices'))->toEqualCanonicalizing([$this->boxes->id => null, $this->oil->id => null]);
});

test('the suggested price is the manual price or the last purchase price times the markup', function () {
    ($this->buy)($this->city, $this->oil, '1', '1000', '2026-10-01');
    ($this->buy)($this->city, $this->oil, '1', '1200', '2026-10-05');
    ($this->buy)($this->city, $this->oil, '1', '900', '2026-10-03'); // saved last, dated before the 5th
    $this->boxes->update(['custom_sale_price' => '75.50']);

    $this->actingAs($this->owner);

    $prices = $this->get('/products')->viewData('suggestedPrices');

    expect($prices[$this->oil->id])->toBe('2400.00')
        ->and($prices[$this->boxes->id])->toBe('75.50');
});

test('the page of suggested prices equals the single-product rule', function () {
    ($this->buy)($this->city, $this->oil, '1', '333.33');
    $pricing = app(PricingService::class);

    $products = Product::orderBy('id')->get();
    $batch = $pricing->suggestedPrices($products);

    foreach ($products as $product) {
        expect($batch[$product->id])->toBe($pricing->suggestedPrice($product));
    }
});

// --- A unit and a party type are fixed once movements exist ----------------------------------------

test('the unit of a product with movements cannot change', function () {
    ($this->buy)($this->city, $this->oil, '5', '100');
    $this->actingAs($this->owner);

    $this->put("/products/{$this->oil->id}", ['name' => 'زيت زيتون', 'unit' => 'kilo'])->assertSessionHasErrors('unit');
    expect($this->oil->fresh()->unit)->toBe('liter');

    // everything else can still change
    $this->put("/products/{$this->oil->id}", ['name' => 'زيت بكر', 'unit' => 'liter', 'location' => 'A1'])->assertSessionHasNoErrors();
    expect($this->oil->fresh()->name)->toBe('زيت بكر');
});

test('the unit of a product without movements can change', function () {
    $this->actingAs($this->owner);

    $this->put("/products/{$this->boxes->id}", ['name' => 'معلبات فول', 'unit' => 'piece'])->assertSessionHasNoErrors();

    expect($this->boxes->fresh()->unit)->toBe('piece');
});

test('the type of a party with movements cannot change, even if the movements are in another warehouse', function () {
    ($this->buy)($this->city, $this->oil, '5', '100');

    // a warehouse user of the other warehouse cannot see the purchase, but the rule still applies
    $this->actingAs($this->rural);
    $this->put("/parties/{$this->supplier->id}", ['name' => $this->supplier->name, 'type' => 'customer'])->assertSessionHasErrors('type');
    expect($this->supplier->fresh()->type->value)->toBe('supplier');

    $this->put("/parties/{$this->supplier->id}", ['name' => 'اسم جديد', 'type' => 'supplier'])->assertSessionHasNoErrors();
    expect($this->supplier->fresh()->name)->toBe('اسم جديد');
});

test('the type of a party without movements can change', function () {
    $idle = Party::factory()->supplier()->for($this->company)->create();
    $this->actingAs($this->owner);

    $this->put("/parties/{$idle->id}", ['name' => $idle->name, 'type' => 'customer'])->assertSessionHasNoErrors();

    expect($idle->fresh()->type->value)->toBe('customer');
});
