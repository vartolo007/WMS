<?php

use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Models\Company;
use App\Models\Movement;
use App\Models\MovementItem;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Services\MovementService;
use App\Services\StockService;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->owner = User::factory()->for($this->company)->create(['name' => 'صاحب الشركة']);
    $this->city = User::factory()->warehouse()->for($this->company)->create(['name' => 'المدينة']);
    $this->rural = User::factory()->warehouse()->for($this->company)->create(['name' => 'الريف']);
    $this->supplier = Party::factory()->supplier()->for($this->company)->create();
    $this->customer = Party::factory()->for($this->company)->create();
    $this->oil = Product::factory()->for($this->company)->create(['name' => 'زيت', 'unit' => 'liter']);

    $this->actingAs($this->owner);
    $this->service = app(MovementService::class);

    $this->purchase = $this->service->purchase($this->owner, ['warehouse_id' => $this->city->id, 'party_id' => $this->supplier->id, 'items' => [['product_id' => $this->oil->id, 'quantity' => '100', 'unit_price' => '100']]]);
    $this->sale = $this->service->sale($this->owner, ['warehouse_id' => $this->city->id, 'party_id' => $this->customer->id, 'items' => [['product_id' => $this->oil->id, 'quantity' => '6', 'unit_price' => '300']]]);
});

test('the owner cancels a movement from its page and comes back with a note', function () {
    $this->from("/movements/{$this->sale->id}")->post("/movements/{$this->sale->id}/cancel")
        ->assertRedirect("/movements/{$this->sale->id}")
        ->assertSessionHas('status', 'تم إلغاء الحركة S-0001.');

    expect($this->sale->fresh()->status)->toBe(MovementStatus::Cancelled)
        ->and(app(StockService::class)->balance($this->oil->id, $this->city->id))->toBe('100.000');
});

test('a refused cancel shows its reason on the page and changes nothing', function () {
    $this->from("/movements/{$this->purchase->id}")->post("/movements/{$this->purchase->id}/cancel")
        ->assertRedirect("/movements/{$this->purchase->id}")
        ->assertSessionHasErrors('cancel');

    $this->get("/movements/{$this->purchase->id}")->assertSee('سالب')->assertSee('زيت');
    expect($this->purchase->fresh()->status)->toBe(MovementStatus::Confirmed);
});

test('the cancelled movement shows who cancelled it and when, without the cancel button', function () {
    $this->post("/movements/{$this->sale->id}/cancel");

    $this->get("/movements/{$this->sale->id}")
        ->assertOk()
        ->assertSee('أُلغيت هالحركة بتاريخ')
        ->assertSee('صاحب الشركة')
        ->assertDontSee('نعم، ألغِ الحركة')
        ->assertDontSee(route('movements.cancel', $this->sale), false);

    $this->post("/movements/{$this->sale->id}/cancel")->assertForbidden();
});

test('an invoice with an active return offers no cancel and explains why', function () {
    $return = $this->service->return($this->owner, $this->sale, ['items' => [['product_id' => $this->oil->id, 'quantity' => '2']]]);

    $this->get("/movements/{$this->sale->id}")->assertOk()->assertSee('ألغِ المرتجع أولاً')->assertDontSee(route('movements.cancel', $this->sale), false);
    $this->get('/movements?type=sale')->assertDontSee(route('movements.cancel', $this->sale), false);

    // refused on the server too
    $this->post("/movements/{$this->sale->id}/cancel")->assertSessionHasErrors('cancel');

    $this->post("/movements/{$return->id}/cancel");
    $this->get("/movements/{$this->sale->id}")->assertSee(route('movements.cancel', $this->sale), false);
});

test('the list offers a cancel for each cancellable row and none for cancelled ones', function () {
    $this->post("/movements/{$this->sale->id}/cancel");

    $this->get('/movements?warehouse_id=')
        ->assertOk()
        ->assertSee(route('movements.cancel', $this->purchase), false)
        ->assertDontSee(route('movements.cancel', $this->sale), false);
});

test('the dialog describes the real stock effect of each type', function () {
    $page = fn (Movement $m) => $this->get("/movements/{$m->id}")->assertOk();

    $page($this->purchase)->assertSee('100 لتر زيت رح ينخصموا من مستودع «المدينة»', false);
    $page($this->sale)->assertSee('6 لتر زيت رح يرجعوا لمستودع «المدينة»', false);

    $saleReturn = $this->service->return($this->owner, $this->sale, ['items' => [['product_id' => $this->oil->id, 'quantity' => '2']]]);
    $page($saleReturn)->assertSee('2 لتر زيت رح ينخصموا من مستودع «المدينة»', false);

    $transfer = Movement::factory()->for($this->company)->create([
        'type' => MovementType::Transfer, 'number' => 1, 'warehouse_id' => $this->city->id, 'target_warehouse_id' => $this->rural->id, 'created_by' => $this->city->id,
    ]);
    MovementItem::factory()->create(['movement_id' => $transfer->id, 'product_id' => $this->oil->id, 'quantity' => '5.000']);
    $page($transfer)->assertSee('5 لتر زيت رح يرجعوا لمستودع «المدينة» وينخصموا من مستودع «الريف»', false);
});

test('a warehouse user cancels only the movements of their own warehouse', function () {
    $ruralPurchase = $this->service->purchase($this->owner, ['warehouse_id' => $this->rural->id, 'party_id' => $this->supplier->id, 'items' => [['product_id' => $this->oil->id, 'quantity' => '5', 'unit_price' => '100']]]);

    $this->actingAs($this->city);
    $this->get("/movements/{$this->sale->id}")->assertSee(route('movements.cancel', $this->sale), false);
    $this->post("/movements/{$ruralPurchase->id}/cancel")->assertNotFound();
    $this->post("/movements/{$this->sale->id}/cancel")->assertRedirect();
    expect($this->sale->fresh()->cancelled_by)->toBe($this->city->id);
});

test('the receiving warehouse sees an incoming transfer without a cancel button and cannot cancel it', function () {
    $transfer = Movement::factory()->for($this->company)->create([
        'type' => MovementType::Transfer, 'number' => 1, 'warehouse_id' => $this->city->id, 'target_warehouse_id' => $this->rural->id, 'created_by' => $this->city->id,
    ]);
    MovementItem::factory()->create(['movement_id' => $transfer->id, 'product_id' => $this->oil->id, 'quantity' => '5.000']);

    $this->actingAs($this->rural);
    $this->get("/movements/{$transfer->id}")->assertOk()->assertDontSee(route('movements.cancel', $transfer), false);
    $this->post("/movements/{$transfer->id}/cancel")->assertForbidden();
});

test('guests and other companies cannot cancel', function () {
    $other = User::factory()->for(Company::factory()->create())->create();

    $this->actingAs($other)->post("/movements/{$this->sale->id}/cancel")->assertNotFound();

    auth()->logout();
    $this->post("/movements/{$this->sale->id}/cancel")->assertRedirect('/login');
    expect($this->sale->fresh()->status)->toBe(MovementStatus::Confirmed);
});
