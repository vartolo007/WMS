<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->companyA = Company::factory()->create();
    $this->companyB = Company::factory()->create();
    $this->ownerA = User::factory()->for($this->companyA)->create();

    $this->productA = Product::factory()->for($this->companyA)->create(['name' => 'منتج أ']);
    $this->productB = Product::factory()->for($this->companyB)->create(['name' => 'منتج ب']);
});

test('a user sees only the products of their company', function () {
    $this->actingAs($this->ownerA);

    expect(Product::pluck('name')->all())->toBe(['منتج أ']);
});

test('a product of another company cannot be found by id', function () {
    $this->actingAs($this->ownerA);

    expect(Product::find($this->productB->id))->toBeNull();
});

test('with no authenticated user no product is returned', function () {
    expect(Product::count())->toBe(0);
});

test('the list page shows only the own company products', function () {
    $this->actingAs($this->ownerA)
        ->get('/products')
        ->assertOk()
        ->assertSee('منتج أ')
        ->assertDontSee('منتج ب');
});

test('a product of another company cannot be edited or toggled', function () {
    $this->actingAs($this->ownerA);

    $this->put("/products/{$this->productB->id}", ['name' => 'مخترق', 'unit' => 'piece'])->assertNotFound();
    $this->patch("/products/{$this->productB->id}/toggle")->assertNotFound();

    $productB = Product::withoutGlobalScopes()->find($this->productB->id);
    expect($productB->name)->toBe('منتج ب')
        ->and($productB->is_active)->toBeTrue();
});

test('company_id comes from the logged-in user, never from the request', function () {
    $this->actingAs($this->ownerA)->post('/products', [
        'name' => 'مادة جديدة',
        'unit' => 'piece',
        'company_id' => $this->companyB->id,
    ])->assertRedirect(route('products.index'));

    $created = Product::withoutGlobalScopes()->where('name', 'مادة جديدة')->sole();
    expect($created->company_id)->toBe($this->companyA->id);
});

test('the same barcode may exist in two companies', function () {
    Product::factory()->for($this->companyA)->create(['barcode' => '123']);
    Product::factory()->for($this->companyB)->create(['barcode' => '123']);

    expect(Product::withoutGlobalScopes()->where('barcode', '123')->count())->toBe(2);
});
