<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->owner = User::factory()->for($this->company)->create();
});

function productData(array $overrides = []): array
{
    return array_merge(['name' => 'زيت زيتون', 'unit' => 'liter'], $overrides);
}

// --- Who may manage the catalogue (Q2) ---------------------------------------------------------

test('the owner and a warehouse user can both add and edit products', function (string $role) {
    $user = $role === 'owner' ? $this->owner : User::factory()->warehouse()->for($this->company)->create();

    $this->actingAs($user)->post('/products', productData())->assertRedirect(route('products.index'));
    $product = Product::sole();

    $this->actingAs($user)->put("/products/{$product->id}", productData(['name' => 'زيت ممتاز']))
        ->assertRedirect(route('products.index'));

    expect($product->fresh()->name)->toBe('زيت ممتاز');
})->with(['owner', 'warehouse']);

test('the platform admin has no access to the catalogue', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/products')
        ->assertForbidden();
});

test('a guest is sent to login', function () {
    $this->get('/products')->assertRedirect('/login');
});

test('a suspended company cannot reach the catalogue', function () {
    $suspended = User::factory()->for(Company::factory()->suspended())->create();

    $this->actingAs($suspended)->get('/products')->assertRedirect('/login');
});

// --- Fields and validation ---------------------------------------------------------------------

test('a product is created with all its fields', function () {
    $this->actingAs($this->owner)->post('/products', productData([
        'barcode' => '6290001000011',
        'location' => 'رف A3',
        'custom_sale_price' => '12.50',
        'min_stock' => '20.500',
    ]))->assertRedirect(route('products.index'));

    $product = Product::sole();
    expect($product->barcode)->toBe('6290001000011')
        ->and($product->unit)->toBe('liter')
        ->and($product->location)->toBe('رف A3')
        ->and($product->custom_sale_price)->toBe('12.50')
        ->and($product->min_stock)->toBe('20.500')
        ->and($product->is_active)->toBeTrue();
});

test('name and unit are required, the unit must come from the config list', function () {
    $this->actingAs($this->owner);

    $this->post('/products', ['name' => '', 'unit' => ''])->assertSessionHasErrors(['name', 'unit']);
    $this->post('/products', productData(['unit' => 'barrel']))->assertSessionHasErrors('unit');

    expect(Product::count())->toBe(0);
});

test('an empty barcode is stored as null and several products may have none', function () {
    $this->actingAs($this->owner);

    $this->post('/products', productData(['name' => 'أ', 'barcode' => '']));
    $this->post('/products', productData(['name' => 'ب', 'barcode' => '']))->assertSessionHasNoErrors();

    expect(Product::whereNull('barcode')->count())->toBe(2);
});

test('the barcode is unique inside the company', function () {
    Product::factory()->for($this->company)->create(['barcode' => '111']);

    $this->actingAs($this->owner)
        ->post('/products', productData(['barcode' => '111']))
        ->assertSessionHasErrors('barcode');

    expect(Product::count())->toBe(1);
});

test('the same barcode is accepted when another company owns it', function () {
    Product::factory()->for(Company::factory())->create(['barcode' => '111']);

    $this->actingAs($this->owner)
        ->post('/products', productData(['barcode' => '111']))
        ->assertSessionHasNoErrors();

    expect(Product::count())->toBe(1);
});

test('editing a product with its own barcode is not a duplicate', function () {
    $product = Product::factory()->for($this->company)->create(['barcode' => '111']);

    $this->actingAs($this->owner)
        ->put("/products/{$product->id}", productData(['barcode' => '111', 'location' => 'رف B']))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->location)->toBe('رف B');
});

test('a unit without fractions needs a whole minimum stock, a unit with fractions does not', function () {
    $this->actingAs($this->owner);

    $this->post('/products', productData(['unit' => 'box', 'min_stock' => '2.5']))->assertSessionHasErrors('min_stock');
    $this->post('/products', productData(['unit' => 'box', 'min_stock' => '3']))->assertSessionHasNoErrors();
    $this->post('/products', productData(['name' => 'سكر', 'unit' => 'kilo', 'min_stock' => '2.5']))->assertSessionHasNoErrors();
});

test('prices and quantities keep their decimal places and cannot be negative', function () {
    $this->actingAs($this->owner);

    $this->post('/products', productData(['custom_sale_price' => '-1']))->assertSessionHasErrors('custom_sale_price');
    $this->post('/products', productData(['custom_sale_price' => '1.234']))->assertSessionHasErrors('custom_sale_price');
    $this->post('/products', productData(['min_stock' => '-1']))->assertSessionHasErrors('min_stock');
    $this->post('/products', productData(['unit' => 'kilo', 'min_stock' => '1.2345']))->assertSessionHasErrors('min_stock');
});

// --- Deactivate instead of delete --------------------------------------------------------------

test('a product is deactivated and reactivated, never deleted', function () {
    $product = Product::factory()->for($this->company)->create();
    $this->actingAs($this->owner);

    $this->patch("/products/{$product->id}/toggle");
    expect($product->fresh()->is_active)->toBeFalse();

    $this->patch("/products/{$product->id}/toggle");
    expect($product->fresh()->is_active)->toBeTrue();

    $this->delete("/products/{$product->id}")->assertStatus(405);
    expect(Product::count())->toBe(1);
});

// --- List and search ---------------------------------------------------------------------------

test('the list hides inactive products unless asked', function () {
    Product::factory()->for($this->company)->create(['name' => 'مادة فعالة']);
    Product::factory()->for($this->company)->inactive()->create(['name' => 'مادة موقوفة']);
    $this->actingAs($this->owner);

    $this->get('/products')->assertSee('مادة فعالة')->assertDontSee('مادة موقوفة');
    $this->get('/products?inactive=1')->assertSee('مادة فعالة')->assertSee('مادة موقوفة');
});

test('search matches part of the name or part of the barcode', function () {
    Product::factory()->for($this->company)->create(['name' => 'شامبو أعشاب', 'barcode' => '6290001']);
    Product::factory()->for($this->company)->create(['name' => 'معجون أسنان', 'barcode' => '7770002']);
    $this->actingAs($this->owner);

    $this->get('/products?q=أعشاب')->assertSee('شامبو أعشاب')->assertDontSee('معجون أسنان');
    $this->get('/products?q=77700')->assertSee('معجون أسنان')->assertDontSee('شامبو أعشاب');
});

test('wildcards typed in the search box are plain text', function () {
    Product::factory()->for($this->company)->create(['name' => 'شامبو أعشاب']);
    $this->actingAs($this->owner);

    $this->get('/products?q=%25')->assertDontSee('شامبو أعشاب');
    $this->get('/products?q=_')->assertDontSee('شامبو أعشاب');
});

test('an empty search result tells the user so', function () {
    $this->actingAs($this->owner)
        ->get('/products?q=غير-موجود')
        ->assertSee('ما في مادة بهالاسم أو الباركود');
});

test('the sidebar links to the catalogue for the owner and the warehouse', function () {
    $this->actingAs($this->owner)->get('/dashboard')->assertSee(route('products.index'));

    $warehouse = User::factory()->warehouse()->for($this->company)->create();
    $this->actingAs($warehouse)->get('/dashboard')->assertSee(route('products.index'));
});

test('the edit form gets stored decimals without padding zeros, so a box can be saved again', function () {
    $product = Product::factory()->for($this->company)->create(['unit' => 'box', 'min_stock' => '12', 'custom_sale_price' => '15']);
    $this->actingAs($this->owner);

    // The edit payload is JSON inside the page: min_stock must be "12", not "12.000" (a box takes whole numbers only).
    // Js::from() writes the quotes as ", so the page text contains that escape literally.
    $this->get('/products')->assertSee('\\u0022min_stock\\u0022:\\u002212\\u0022', false);

    $this->put("/products/{$product->id}", productData(['unit' => 'box', 'min_stock' => '12', 'custom_sale_price' => '15']))
        ->assertSessionHasNoErrors();
});
