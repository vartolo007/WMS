<?php

use App\Enums\MovementType;
use App\Models\Company;
use App\Models\Movement;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Services\MovementService;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->owner = User::factory()->for($this->company)->create();
    $this->city = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع المدينة']);
    $this->rural = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع الريف']);
    User::factory()->warehouse()->inactive()->for($this->company)->create(['name' => 'مستودع متوقف']);
    $supplier = Party::factory()->supplier()->for($this->company)->create(['name' => 'مورد الشام']);
    $this->oil = Product::factory()->for($this->company)->create(['name' => 'زيت', 'unit' => 'liter']);

    $this->actingAs($this->owner);
    $this->purchase = app(MovementService::class)->purchase($this->owner, ['warehouse_id' => $this->city->id, 'party_id' => $supplier->id, 'items' => [['product_id' => $this->oil->id, 'quantity' => '40', 'unit_price' => '100']]]);
});

test('the transfer screen opens for the owner and the warehouse user, with a receiving warehouse and no money fields', function () {
    foreach ([$this->owner, $this->city] as $user) {
        $this->actingAs($user)->get('/movements/create?type=transfer')
            ->assertOk()
            ->assertSee('المستودع المستقبِل')
            ->assertSee('مستودع الريف')
            // the owner's top bar lists every warehouse, so the stopped one is checked in the data of the screen
            ->assertViewHas('targetWarehouses', fn ($warehouses) => ! $warehouses->pluck('name')->contains('مستودع متوقف'))
            ->assertDontSee('سعر الوحدة')
            ->assertDontSee('الحسم')
            ->assertDontSee('رقم الوثيقة')
            ->assertSee('عدد البنود');
    }
});

test('the other types keep their money fields and no receiving warehouse', function () {
    $this->get('/movements/create?type=sale')->assertOk()->assertSee('سعر الوحدة')->assertSee('الحسم')->assertDontSee('المستودع المستقبِل');
    $this->get('/movements/create?type=purchase')->assertOk()->assertSee('رقم الوثيقة')->assertDontSee('المستودع المستقبِل');
});

test('the tabs include the transfer and the dashboards have the transfer button', function () {
    $link = route('movements.create', ['type' => 'transfer']);

    $this->get('/movements/create?type=sale')->assertSee($link, false)->assertSee('نقل');

    foreach ([$this->owner, $this->city] as $user) {
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee($link, false)->assertSee('+ نقل');
    }
});

test('a saved transfer opens with its counterpart, no money, and the right cancel button for each side', function () {
    $this->postJson('/movements', ['type' => 'transfer', 'warehouse_id' => $this->city->id, 'target_warehouse_id' => $this->rural->id, 'items' => [['product_id' => $this->oil->id, 'quantity' => '5']]])->assertOk();
    $transfer = Movement::where('type', MovementType::Transfer)->sole();

    $this->get("/movements/{$transfer->id}")
        ->assertOk()
        ->assertSee('T-0001')
        ->assertSee('← مستودع الريف')
        ->assertSee(route('movements.cancel', $transfer), false);

    $this->get("/movements/{$transfer->id}/print")->assertOk()->assertSee('نقل بين مستودعين')->assertSee('توقيع المستلم');

    // the receiving warehouse sees it in its list and details, without a cancel button
    $this->actingAs($this->rural);
    $this->get('/movements')->assertSee('T-0001');
    $this->get("/movements/{$transfer->id}")->assertOk()->assertDontSee(route('movements.cancel', $transfer), false);
});

test('a transfer shows an estimated value, not a payment', function () {
    $this->postJson('/movements', ['type' => 'transfer', 'warehouse_id' => $this->city->id, 'target_warehouse_id' => $this->rural->id, 'items' => [['product_id' => $this->oil->id, 'quantity' => '5']]])->assertOk();
    $transfer = Movement::where('type', MovementType::Transfer)->sole();

    foreach (["/movements/{$transfer->id}", "/movements/{$transfer->id}/print"] as $url) {
        $this->get($url)->assertOk()->assertSee('القيمة التقديرية')->assertSee('500.00')->assertDontSee('نقدي')->assertDontSee('الحسم');
    }
});
