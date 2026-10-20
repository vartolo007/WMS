<?php

use App\Enums\MovementType;
use App\Models\Company;
use App\Models\Movement;
use App\Models\Operation;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Services\MovementService;
use App\Services\StockService;

beforeEach(function () {
    $this->company = Company::factory()->create(['markup_percent' => 200, 'currency_symbol' => 'ل.س']);
    $this->owner = User::factory()->for($this->company)->create();
    $this->warehouse = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع المدينة']);
    $this->other = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع الريف']);
    $this->supplier = Party::factory()->supplier()->for($this->company)->create(['name' => 'مورد الشام']);
    $this->customer = Party::factory()->for($this->company)->create(['name' => 'سامر الحلبي']);
    $this->oil = Product::factory()->for($this->company)->create(['name' => 'زيت زيتون', 'barcode' => '6210001', 'unit' => 'liter']);
    $this->box = Product::factory()->for($this->company)->create(['name' => 'معلبات حمص', 'barcode' => '6210003', 'unit' => 'box']);

    $this->stock = function (User $warehouse, Product $product, string $quantity, string $price = '1000') {
        $this->actingAs($this->owner);

        return app(MovementService::class)->purchase($this->owner, [
            'warehouse_id' => $warehouse->id,
            'party_id' => $this->supplier->id,
            'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $price]],
        ]);
    };

    $this->payload = fn (array $overrides = []) => [
        'type' => 'sale',
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_id' => $this->oil->id, 'quantity' => '1.25']],
        ...$overrides,
    ];
});

test('guests are sent to the login page', function () {
    $this->get('/movements')->assertRedirect('/login');
    $this->get('/movements/create')->assertRedirect('/login');
    $this->postJson('/movements', [])->assertUnauthorized();
});

test('the platform owner has no access to movements', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get('/movements')->assertForbidden();
    $this->actingAs($admin)->get('/movements/create')->assertForbidden();
});

test('the invoice screen opens for both types and refuses unknown ones', function () {
    $this->actingAs($this->owner);

    $this->get('/movements/create?type=sale')->assertOk()->assertSee('حركة جديدة')->assertSee('سامر الحلبي')->assertDontSee('مورد الشام');
    $this->get('/movements/create?type=purchase')->assertOk()->assertSee('مورد الشام')->assertDontSee('سامر الحلبي');
    $this->get('/movements/create?type=sale_return')->assertNotFound();
    $this->get('/movements/create?type=nonsense')->assertNotFound();

    $this->actingAs($this->warehouse)->get('/movements/create')->assertOk();
});

test('an owner without warehouses is asked to add one', function () {
    $company = Company::factory()->create();
    $lonelyOwner = User::factory()->for($company)->create();

    $this->actingAs($lonelyOwner)->get('/movements/create')->assertOk()->assertSee('أضف أول مستودع');
});

test('saving a purchase posts it and answers with the details page', function () {
    $this->actingAs($this->owner);

    $response = $this->postJson('/movements', ($this->payload)([
        'type' => 'purchase',
        'party_id' => $this->supplier->id,
        'document_no' => 'SH-2291',
        'items' => [['product_id' => $this->oil->id, 'quantity' => '40', 'unit_price' => '9999.75']],
    ]));

    $movement = Movement::sole();
    $response->assertOk()->assertJson(['redirect' => route('movements.show', $movement)]);

    expect($movement->type)->toBe(MovementType::Purchase)
        ->and($movement->document_no)->toBe('SH-2291')
        ->and($movement->total)->toBe('399990.00')
        ->and($movement->created_by)->toBe($this->owner->id)
        ->and(app(StockService::class)->balance($this->oil->id, $this->warehouse->id))->toBe('40.000');
});

test('saving a sale uses the suggested price and reduces the stock', function () {
    ($this->stock)($this->warehouse, $this->oil, '10', '1000');
    $this->actingAs($this->warehouse);

    $this->postJson('/movements', ($this->payload)(['warehouse_id' => $this->other->id]))->assertOk();

    $sale = Movement::where('type', MovementType::Sale)->sole();

    // the warehouse user's own warehouse wins over the id in the request
    expect($sale->warehouse_id)->toBe($this->warehouse->id)
        ->and($sale->items->first()->unit_price)->toBe('2000.00')
        ->and($sale->total)->toBe('2500.00')
        ->and(app(StockService::class)->balance($this->oil->id, $this->warehouse->id))->toBe('8.750');
});

test('a sale beyond the stock is refused with the message on the line', function () {
    ($this->stock)($this->warehouse, $this->oil, '1');
    $this->actingAs($this->owner);

    $this->postJson('/movements', ($this->payload)())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.quantity']);

    expect(Movement::where('type', MovementType::Sale)->count())->toBe(0);
});

test('the shape of an invoice is validated', function () {
    ($this->stock)($this->warehouse, $this->box, '10');
    $this->actingAs($this->owner);

    $this->postJson('/movements', ($this->payload)(['items' => []]))->assertJsonValidationErrors(['items']);
    $this->postJson('/movements', ($this->payload)(['type' => 'sale_return']))->assertJsonValidationErrors(['type']);
    $this->postJson('/movements', ($this->payload)(['warehouse_id' => null]))->assertJsonValidationErrors(['warehouse_id']);
    $this->postJson('/movements', ($this->payload)(['items' => [['product_id' => $this->oil->id, 'quantity' => '0']]]))->assertJsonValidationErrors(['items.0.quantity']);
    $this->postJson('/movements', ($this->payload)(['items' => [['product_id' => $this->oil->id, 'quantity' => '-1']]]))->assertJsonValidationErrors(['items.0.quantity']);
    $this->postJson('/movements', ($this->payload)(['items' => [['product_id' => $this->oil->id, 'quantity' => '1.2345']]]))->assertJsonValidationErrors(['items.0.quantity']);
    $this->postJson('/movements', ($this->payload)(['items' => [['product_id' => $this->oil->id, 'quantity' => '1', 'unit_price' => '-5']]]))->assertJsonValidationErrors(['items.0.unit_price']);
    $this->postJson('/movements', ($this->payload)(['items' => [['product_id' => $this->oil->id, 'quantity' => '1', 'unit_price' => '1.234']]]))->assertJsonValidationErrors(['items.0.unit_price']);
    $this->postJson('/movements', ($this->payload)(['discount' => '-1']))->assertJsonValidationErrors(['discount']);

    // a unit without fractions takes whole quantities only
    $this->postJson('/movements', ($this->payload)(['items' => [['product_id' => $this->box->id, 'quantity' => '1.5']]]))->assertJsonValidationErrors(['items.0.quantity']);
    $this->postJson('/movements', ($this->payload)(['items' => [['product_id' => $this->box->id, 'quantity' => '2']]]))->assertOk();
});

test('a discount above the subtotal is refused', function () {
    $this->actingAs($this->owner);

    $this->postJson('/movements', ($this->payload)([
        'type' => 'purchase',
        'party_id' => $this->supplier->id,
        'discount' => '100.01',
        'items' => [['product_id' => $this->oil->id, 'quantity' => '1', 'unit_price' => '100']],
    ]))->assertJsonValidationErrors(['discount']);
});

test('the list shows movements, filters them and searches by number and party', function () {
    $purchase = ($this->stock)($this->warehouse, $this->oil, '10');
    app(MovementService::class)->sale($this->owner, [
        'warehouse_id' => $this->warehouse->id,
        'party_id' => $this->customer->id,
        'items' => [['product_id' => $this->oil->id, 'quantity' => '1']],
    ]);

    $this->actingAs($this->owner);

    $this->get('/movements?warehouse_id=')->assertOk()->assertSee('P-0001')->assertSee('S-0001');
    $this->get('/movements?warehouse_id=&type=sale')->assertSee('S-0001')->assertDontSee('P-0001');
    $this->get('/movements?warehouse_id=&q=S-0001')->assertSee('S-0001')->assertDontSee('P-0001');
    $this->get('/movements?warehouse_id=&q=الحلبي')->assertSee('S-0001')->assertDontSee('P-0001');
    $this->get('/movements?warehouse_id=&q=مورد الشام')->assertSee('P-0001')->assertDontSee('S-0001');
    $this->get('/movements?warehouse_id=&status=cancelled')->assertSee('ما في حركات بهالفلتر');
    $this->get('/movements?warehouse_id=&type=bogus')->assertSessionHasErrors('type');

    expect($purchase->number)->toBe(1);
});

test('the owner list follows the warehouse chosen in the top bar until the filter says otherwise', function () {
    ($this->stock)($this->warehouse, $this->oil, '10');
    ($this->stock)($this->other, $this->box, '5');

    $this->actingAs($this->owner)->withSession(['warehouse_id' => $this->other->id]);

    $this->get('/movements')->assertSee('مستودع الريف')->assertViewHas('movements', fn ($p) => $p->total() === 1);
    $this->get('/movements?warehouse_id=')->assertViewHas('movements', fn ($p) => $p->total() === 2);
    $this->get('/movements?warehouse_id='.$this->warehouse->id)->assertViewHas('movements', fn ($p) => $p->total() === 1);
});

test('a warehouse user sees only their own movements and cannot open others', function () {
    $mine = ($this->stock)($this->warehouse, $this->oil, '10');
    $theirs = ($this->stock)($this->other, $this->box, '5');

    $this->actingAs($this->warehouse);

    $this->get('/movements')->assertOk()->assertViewHas('movements', fn ($p) => $p->total() === 1);
    $this->get("/movements/{$mine->id}")->assertOk();
    $this->get("/movements/{$mine->id}/print")->assertOk();
    $this->get("/movements/{$theirs->id}")->assertNotFound();
    $this->get("/movements/{$theirs->id}/print")->assertNotFound();
});

test('a movement of another company cannot be opened', function () {
    $foreignCompany = Company::factory()->create();
    $foreign = Movement::factory()->for($foreignCompany)->create();

    $this->actingAs($this->owner);

    $this->get("/movements/{$foreign->id}")->assertNotFound();
    $this->get("/movements/{$foreign->id}/print")->assertNotFound();
});

test('the details page shows the lines and the totals', function () {
    $this->actingAs($this->owner);
    $movement = app(MovementService::class)->purchase($this->owner, [
        'warehouse_id' => $this->warehouse->id,
        'party_id' => $this->supplier->id,
        'discount' => '1000',
        'items' => [
            ['product_id' => $this->oil->id, 'quantity' => '1', 'unit_price' => '6000'],
            ['product_id' => $this->box->id, 'quantity' => '1', 'unit_price' => '4000'],
        ],
    ]);

    $this->get("/movements/{$movement->id}")
        ->assertOk()
        ->assertSee('P-0001')
        ->assertSee('زيت زيتون')
        ->assertSee('معلبات حمص')
        ->assertSee('10,000.00')
        ->assertSee('9,000.00')
        ->assertSee('المدفوع نقداً');
});

test('the print page carries the company header and a stamp only for cancelled movements', function () {
    $movement = ($this->stock)($this->warehouse, $this->oil, '3');
    $cancelled = Movement::factory()->for($this->company)->cancelled()->create([
        'warehouse_id' => $this->warehouse->id,
        'created_by' => $this->warehouse->id,
        'number' => 77,
    ]);

    $this->actingAs($this->owner);

    $this->get("/movements/{$movement->id}/print")
        ->assertOk()
        ->assertSee($this->company->name)
        ->assertSee('مستودع المدينة')
        ->assertSee('فاتورة شراء')
        ->assertSee('توقيع المورد')
        ->assertDontSee('ملغاة');

    $this->get("/movements/{$cancelled->id}/print")->assertOk()->assertSee('ملغاة');
});

test('the lookup finds a product by barcode or name and reports balance and prices', function () {
    ($this->stock)($this->warehouse, $this->oil, '10', '1000');
    $this->actingAs($this->owner);

    $byBarcode = $this->getJson('/products/lookup?q=6210001&warehouse_id='.$this->warehouse->id)->assertOk();

    $byBarcode->assertJson([
        'id' => $this->oil->id,
        'name' => 'زيت زيتون',
        'allows_fraction' => true,
        'balance' => '10.000',
        'last_purchase_price' => '1000.00',
        'suggested_price' => '2000.00',
    ]);

    $this->getJson('/products/lookup?q=زيت&warehouse_id='.$this->warehouse->id)->assertOk()->assertJson(['id' => $this->oil->id]);
    $this->getJson('/products/lookup?product_id='.$this->box->id.'&warehouse_id='.$this->other->id)->assertOk()->assertJson(['balance' => '0.000', 'allows_fraction' => false]);
    $this->getJson('/products/lookup?q=6210001')->assertOk()->assertJson(['balance' => null]);
});

test('the lookup reports unknown and stopped products', function () {
    Product::factory()->inactive()->for($this->company)->create(['name' => 'مادة موقوفة', 'barcode' => '999']);
    $this->actingAs($this->owner);

    $this->getJson('/products/lookup?q=000000')->assertNotFound()->assertJsonStructure(['message']);
    $this->getJson('/products/lookup?q=999')->assertUnprocessable()->assertJsonStructure(['message']);
    $this->getJson('/products/lookup')->assertUnprocessable();
});

test('the lookup never leaks other companies or other warehouses', function () {
    $foreignCompany = Company::factory()->create();
    Product::factory()->for($foreignCompany)->create(['barcode' => '5550001']);
    $foreignWarehouse = User::factory()->warehouse()->for($foreignCompany)->create();
    ($this->stock)($this->other, $this->oil, '7');

    $this->actingAs($this->owner);
    $this->getJson('/products/lookup?q=5550001')->assertNotFound();
    // a warehouse of another company is ignored: no balance, no error
    $this->getJson('/products/lookup?q=6210001&warehouse_id='.$foreignWarehouse->id)->assertOk()->assertJson(['balance' => null]);

    // a warehouse user always gets their own balance, whatever they ask for
    $this->actingAs($this->warehouse);
    $this->getJson('/products/lookup?q=6210001&warehouse_id='.$this->other->id)->assertOk()->assertJson(['balance' => '0.000']);

    expect(Operation::withoutGlobalScopes()->count())->toBe(1);
});

test('the dashboards and the sidebar lead to the invoice screens', function () {
    foreach ([$this->owner, $this->warehouse] as $user) {
        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee(route('movements.create', ['type' => 'sale']), false)
            ->assertSee(route('movements.create', ['type' => 'purchase']), false)
            ->assertSee(route('movements.index'), false);
    }
});
