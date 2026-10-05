<?php

use App\Models\User;
use App\Support\CurrentWarehouse;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->city = User::factory()->warehouse()->for($this->owner->company)->create(['name' => 'مستودع المدينة']);
});

test('the owner works on all warehouses by default', function () {
    $this->actingAs($this->owner)->get('/dashboard')->assertSee('كل المستودعات');

    expect(app(CurrentWarehouse::class)->get())->toBeNull();
});

test('the owner picks a warehouse and the choice is kept', function () {
    $this->actingAs($this->owner)
        ->from('/dashboard')
        ->post('/warehouse-context', ['warehouse_id' => $this->city->id])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('warehouse_id', $this->city->id);

    expect(app(CurrentWarehouse::class)->id())->toBe($this->city->id);
});

test('an empty choice goes back to all warehouses', function () {
    $this->actingAs($this->owner)->withSession(['warehouse_id' => $this->city->id])
        ->post('/warehouse-context', ['warehouse_id' => ''])
        ->assertSessionMissing('warehouse_id');
});

test('a warehouse of another company cannot be picked', function () {
    $foreign = User::factory()->warehouse()->create();

    $this->actingAs($this->owner)
        ->post('/warehouse-context', ['warehouse_id' => $foreign->id])
        ->assertSessionHasErrors('warehouse_id')
        ->assertSessionMissing('warehouse_id');
});

test('only warehouse accounts can be picked', function () {
    $otherOwner = User::factory()->for($this->owner->company)->create();

    $this->actingAs($this->owner)
        ->post('/warehouse-context', ['warehouse_id' => $otherOwner->id])
        ->assertSessionHasErrors('warehouse_id');
});

test('a stale session value from another company is ignored', function () {
    $foreign = User::factory()->warehouse()->create();

    $this->actingAs($this->owner)->withSession(['warehouse_id' => $foreign->id])->get('/dashboard');

    expect(app(CurrentWarehouse::class)->get())->toBeNull();
});

test('a warehouse user always works on its own warehouse and cannot switch', function () {
    $this->actingAs($this->city)
        ->post('/warehouse-context', ['warehouse_id' => null])
        ->assertForbidden();

    expect(app(CurrentWarehouse::class)->id())->toBe($this->city->id);
});
