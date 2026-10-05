<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->owner = User::factory()->create();
});

function warehouseData(array $overrides = []): array
{
    return array_merge([
        'name' => 'مستودع المدينة',
        'email' => 'city@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides);
}

test('the owner creates a warehouse account in their company', function () {
    $this->actingAs($this->owner)
        ->post('/warehouses', warehouseData())
        ->assertRedirect(route('warehouses.index'));

    $warehouse = User::where('email', 'city@example.com')->sole();
    expect($warehouse->role)->toBe(Role::Warehouse)
        ->and($warehouse->company_id)->toBe($this->owner->company_id)
        ->and($warehouse->is_active)->toBeTrue();
});

test('the created warehouse can log in', function () {
    $this->actingAs($this->owner)->post('/warehouses', warehouseData());
    $this->post('/logout');

    $this->post('/login', ['email' => 'city@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
});

test('warehouse email must be unique on the platform', function () {
    User::factory()->create(['email' => 'city@example.com']);

    $this->actingAs($this->owner)
        ->post('/warehouses', warehouseData())
        ->assertSessionHasErrors('email');
});

test('the owner edits a warehouse and keeps the password when left empty', function () {
    $warehouse = User::factory()->warehouse()->for($this->owner->company)->create();
    $oldHash = $warehouse->password;

    $this->actingAs($this->owner)
        ->put("/warehouses/{$warehouse->id}", ['name' => 'مستودع الريف', 'email' => $warehouse->email, 'password' => ''])
        ->assertRedirect(route('warehouses.index'));

    $warehouse->refresh();
    expect($warehouse->name)->toBe('مستودع الريف')
        ->and($warehouse->password)->toBe($oldHash);
});

test('the owner changes a warehouse password', function () {
    $warehouse = User::factory()->warehouse()->for($this->owner->company)->create();

    $this->actingAs($this->owner)->put("/warehouses/{$warehouse->id}", [
        'name' => $warehouse->name,
        'email' => $warehouse->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    expect(Hash::check('new-password', $warehouse->fresh()->password))->toBeTrue();
});

test('the owner deactivates and reactivates a warehouse', function () {
    $warehouse = User::factory()->warehouse()->for($this->owner->company)->create();

    $this->actingAs($this->owner)->patch("/warehouses/{$warehouse->id}/toggle");
    expect($warehouse->fresh()->is_active)->toBeFalse();

    $this->patch("/warehouses/{$warehouse->id}/toggle");
    expect($warehouse->fresh()->is_active)->toBeTrue();
});

test('the list shows only warehouses of the owner company', function () {
    User::factory()->warehouse()->for($this->owner->company)->create(['name' => 'مستودعنا']);
    User::factory()->warehouse()->create(['name' => 'مستودع شركة أخرى']);

    $this->actingAs($this->owner)->get('/warehouses')
        ->assertOk()
        ->assertSee('مستودعنا')
        ->assertDontSee('مستودع شركة أخرى');
});

test('an owner cannot touch another company warehouse', function () {
    $foreign = User::factory()->warehouse()->create();
    $this->actingAs($this->owner);

    $this->get("/warehouses/{$foreign->id}/edit")->assertForbidden();
    $this->put("/warehouses/{$foreign->id}", warehouseData(['email' => $foreign->email]))->assertForbidden();
    $this->patch("/warehouses/{$foreign->id}/toggle")->assertForbidden();

    $foreign->refresh();
    expect($foreign->is_active)->toBeTrue()
        ->and($foreign->name)->not->toBe('مستودع المدينة');
});

test('the owner cannot edit owner or admin accounts through warehouse pages', function () {
    $otherOwner = User::factory()->for($this->owner->company)->create();
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($this->owner);

    $this->get("/warehouses/{$otherOwner->id}/edit")->assertForbidden();
    $this->patch("/warehouses/{$admin->id}/toggle")->assertForbidden();
    $this->patch("/warehouses/{$this->owner->id}/toggle")->assertForbidden();
});
