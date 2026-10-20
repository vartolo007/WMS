<?php

use App\Models\Company;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->owner = User::factory()->for($this->company)->create();
    $this->city = User::factory()->warehouse()->for($this->company)->create();
    $this->actingAs($this->owner);
});

test('a product can be added from the invoice screen and comes back as JSON', function () {
    $response = $this->postJson('/products', ['name' => 'زيت جديد', 'barcode' => '777001', 'unit' => 'liter', 'location' => 'A1'])
        ->assertCreated()
        ->assertJsonStructure(['id', 'name']);

    $product = Product::findOrFail($response->json('id'));

    expect($product->company_id)->toBe($this->company->id)
        ->and($product->barcode)->toBe('777001')
        ->and($product->unit)->toBe('liter')
        ->and($response->json('name'))->toBe('زيت جديد');
});

test('the quick product form follows the catalogue rules', function () {
    Product::factory()->for($this->company)->create(['barcode' => '777001']);

    $this->postJson('/products', ['name' => 'مكرر', 'barcode' => '777001', 'unit' => 'liter'])->assertUnprocessable()->assertJsonValidationErrors(['barcode']);
    $this->postJson('/products', ['barcode' => '1', 'unit' => 'liter'])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    $this->postJson('/products', ['name' => 'س', 'unit' => 'nonsense'])->assertUnprocessable()->assertJsonValidationErrors(['unit']);
    expect(Product::count())->toBe(1);
});

test('a warehouse user may add a product or a party (Q2), a guest may not', function () {
    $this->actingAs($this->city);
    $this->postJson('/products', ['name' => 'من المستودع', 'unit' => 'piece'])->assertCreated();
    $this->postJson('/parties', ['name' => 'عميل من المستودع', 'type' => 'customer'])->assertCreated();

    auth()->logout();
    $this->postJson('/products', ['name' => 'x', 'unit' => 'piece'])->assertUnauthorized();
    $this->postJson('/parties', ['name' => 'x', 'type' => 'customer'])->assertUnauthorized();
});

test('a party can be added from the invoice screen and comes back as JSON', function () {
    $response = $this->postJson('/parties', ['name' => 'مورد جديد', 'phone' => '0944 111 222', 'type' => 'supplier'])
        ->assertCreated()
        ->assertJson(['name' => 'مورد جديد', 'type' => 'supplier']);

    $party = Party::findOrFail($response->json('id'));

    expect($party->company_id)->toBe($this->company->id)
        ->and($party->phone)->toBe('0944 111 222');

    $this->postJson('/parties', ['name' => '', 'type' => 'supplier'])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    $this->postJson('/parties', ['name' => 'س', 'type' => 'planet'])->assertUnprocessable()->assertJsonValidationErrors(['type']);
});

test('the forms still redirect for the catalogue pages', function () {
    $this->post('/products', ['name' => 'عادي', 'unit' => 'piece'])->assertRedirect(route('products.index'));
    $this->post('/parties', ['name' => 'عادي', 'type' => 'customer'])->assertRedirect(route('parties.index'));
});

test('the invoice screen offers the quick forms where they make sense', function () {
    $purchase = $this->get('/movements/create?type=purchase')->assertOk();
    $purchase->assertSee('أضف هالمادة')->assertSee('quickParty.open', false)->assertSee('إضافة المورد جديد');

    // a sale takes a customer on the spot, but a brand new product has no stock to sell
    $this->get('/movements/create?type=sale')->assertOk()->assertSee('إضافة العميل جديد')->assertDontSee('أضف هالمادة');

    // a transfer has neither a party nor a product to create
    $this->get('/movements/create?type=transfer')->assertOk()->assertDontSee('quickParty.open', false)->assertDontSee('أضف هالمادة');
});
