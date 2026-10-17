<?php

use App\Models\Company;
use App\Models\Movement;
use App\Models\MovementItem;
use App\Models\Operation;
use App\Models\User;

beforeEach(function () {
    $this->companyA = Company::factory()->create();
    $this->companyB = Company::factory()->create();

    $this->ownerA = User::factory()->for($this->companyA)->create();
    $this->warehouse1 = User::factory()->warehouse()->for($this->companyA)->create();
    $this->warehouse2 = User::factory()->warehouse()->for($this->companyA)->create();

    $this->movement1 = Movement::factory()->for($this->companyA)->create(['warehouse_id' => $this->warehouse1->id, 'created_by' => $this->warehouse1->id, 'number' => 1]);
    $this->movement2 = Movement::factory()->for($this->companyA)->create(['warehouse_id' => $this->warehouse2->id, 'created_by' => $this->warehouse2->id, 'number' => 2]);
    $this->movementB = Movement::factory()->for($this->companyB)->create(['number' => 3]);

    foreach ([$this->movement1, $this->movement2, $this->movementB] as $movement) {
        $item = MovementItem::factory()->create(['movement_id' => $movement->id]);
        Operation::factory()->create(['movement_item_id' => $item->id]);
    }
});

test('an owner sees the movements and operations of the whole company only', function () {
    $this->actingAs($this->ownerA);

    expect(Movement::pluck('id')->all())->toEqualCanonicalizing([$this->movement1->id, $this->movement2->id])
        ->and(Operation::count())->toBe(2);
});

test('a movement of another company cannot be found by id', function () {
    $this->actingAs($this->ownerA);

    expect(Movement::find($this->movementB->id))->toBeNull();
});

test('with no authenticated user nothing is returned', function () {
    expect(Movement::count())->toBe(0)
        ->and(Operation::count())->toBe(0);
});

test('a warehouse user sees only the movements and operations of their warehouse', function () {
    $this->actingAs($this->warehouse1);

    expect(Movement::pluck('id')->all())->toBe([$this->movement1->id])
        ->and(Operation::pluck('warehouse_id')->all())->toBe([$this->warehouse1->id])
        ->and(Movement::find($this->movement2->id))->toBeNull();
});

test('the receiving warehouse sees an incoming transfer but not its operations in the source', function () {
    $transfer = Movement::factory()->for($this->companyA)->create([
        'warehouse_id' => $this->warehouse1->id,
        'target_warehouse_id' => $this->warehouse2->id,
        'created_by' => $this->warehouse1->id,
        'number' => 10,
    ]);

    $this->actingAs($this->warehouse2);

    expect(Movement::find($transfer->id))->not->toBeNull();

    $this->actingAs($this->warehouse1);
    expect(Movement::find($transfer->id))->not->toBeNull();
});
