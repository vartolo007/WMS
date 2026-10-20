<?php

use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Models\Company;
use App\Models\Movement;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Services\MovementService;
use App\Services\StockService;

beforeEach(function () {
    $this->company = Company::factory()->create(['currency_symbol' => 'ل.س']);
    $this->owner = User::factory()->for($this->company)->create();
    $this->city = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع المدينة']);
    $this->rural = User::factory()->warehouse()->for($this->company)->create(['name' => 'مستودع الريف']);
    $this->supplier = Party::factory()->supplier()->for($this->company)->create(['name' => 'مورد الشام']);
    $this->customer = Party::factory()->for($this->company)->create(['name' => 'سامر الحلبي']);
    $this->oil = Product::factory()->for($this->company)->create(['name' => 'زيت زيتون', 'unit' => 'liter']);
    $this->boxes = Product::factory()->for($this->company)->create(['name' => 'معلبات فول', 'unit' => 'box']);

    $this->actingAs($this->owner);
    $service = app(MovementService::class);

    $this->buy = fn (User $warehouse, array $items) => $service->purchase($this->owner, ['warehouse_id' => $warehouse->id, 'party_id' => $this->supplier->id, 'items' => $items]);
    $this->sell = fn (User $warehouse, array $items, array $extra = []) => $service->sale($this->owner, ['warehouse_id' => $warehouse->id, 'party_id' => $this->customer->id, 'items' => $items, ...$extra]);

    ($this->buy)($this->city, [
        ['product_id' => $this->oil->id, 'quantity' => '100', 'unit_price' => '1000'],
        ['product_id' => $this->boxes->id, 'quantity' => '50', 'unit_price' => '100'],
    ]);
    ($this->buy)($this->rural, [['product_id' => $this->oil->id, 'quantity' => '10', 'unit_price' => '1000']]);

    // 6,000 + 4,000, discount 1,000
    $this->sale = ($this->sell)($this->city, [
        ['product_id' => $this->oil->id, 'quantity' => '6', 'unit_price' => '1000'],
        ['product_id' => $this->boxes->id, 'quantity' => '40', 'unit_price' => '100'],
    ], ['discount' => '1000']);
});

test('the search step lists confirmed invoices of the right type and finds them by number', function () {
    $purchase = Movement::where('type', MovementType::Purchase)->orderBy('id')->first();

    $this->get('/returns/create?type=sale_return')->assertOk()->assertSee('S-0001')->assertDontSee('P-0001');
    $this->get('/returns/create?type=purchase_return')->assertOk()->assertSee('P-0001')->assertDontSee('S-0001');
    $this->get('/returns/create?type=sale_return&q=S-0001')->assertSee('S-0001');
    $this->get('/returns/create?type=sale_return&q=الحلبي')->assertSee('S-0001');
    $this->get('/returns/create?type=sale_return&q=P-0001')->assertSee('ما في فاتورة بهالرقم');
    $this->get('/returns/create?type=sale')->assertNotFound();
    $this->get('/returns/create?type=transfer')->assertNotFound();

    expect($purchase)->not->toBeNull();
});

test('a cancelled invoice is not offered and a fully returned one is marked', function () {
    Movement::factory()->for($this->company)->cancelled()->create([
        'type' => MovementType::Sale, 'number' => 99, 'warehouse_id' => $this->city->id, 'created_by' => $this->city->id,
    ]);
    app(MovementService::class)->return($this->owner, $this->sale, ['items' => [
        ['product_id' => $this->oil->id, 'quantity' => '6'],
        ['product_id' => $this->boxes->id, 'quantity' => '40'],
    ]]);

    $this->get('/returns/create?type=sale_return')->assertDontSee('S-0099')->assertSee('أُرجعت بالكامل');
    $this->get("/movements/{$this->sale->id}/return")->assertRedirect(route('movements.show', $this->sale));
});

test('the return form shows the original lines with the returnable quantity, and the stock for a purchase return', function () {
    $this->get("/movements/{$this->sale->id}/return")
        ->assertOk()
        // the lines travel to Alpine as JSON, so check them in the view data
        ->assertViewHas('lines', fn ($lines) => $lines->keys()->sort()->values()->all() === collect([$this->oil->id, $this->boxes->id])->sort()->values()->all()
            && $lines[$this->oil->id]['returnable'] === '6.000')
        ->assertSee('الفاتورة الأصلية')
        ->assertSee('S-0001')
        ->assertSee('سامر الحلبي')
        ->assertSee('مستودع المدينة')
        ->assertDontSee('الرصيد المتوفر');

    $purchase = Movement::where('type', MovementType::Purchase)->orderBy('id')->first();
    $this->get("/movements/{$purchase->id}/return")->assertOk()->assertSee('الرصيد المتوفر')->assertSee('مورد الشام');
});

test('saving a return answers with the details page and keeps the proportional discount', function () {
    // 2,000 of the 10,000 invoice paid as 9,000 → 1,800
    $response = $this->postJson("/movements/{$this->sale->id}/return", ['items' => [['product_id' => $this->oil->id, 'quantity' => '2']]]);

    $return = Movement::where('type', MovementType::SaleReturn)->sole();
    $response->assertOk()->assertJson(['redirect' => route('movements.show', $return)]);

    expect($return->total)->toBe('1800.00')
        ->and($return->discount)->toBe('200.00')
        ->and(app(StockService::class)->balance($this->oil->id, $this->city->id))->toBe('96.000');
});

test('a return beyond the returnable quantity is refused on its line', function () {
    $this->postJson("/movements/{$this->sale->id}/return", ['items' => [['product_id' => $this->oil->id, 'quantity' => '6.001']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.quantity']);

    $this->postJson("/movements/{$this->sale->id}/return", ['items' => [['product_id' => $this->oil->id, 'quantity' => '4']]])->assertOk();
    $this->postJson("/movements/{$this->sale->id}/return", ['items' => [['product_id' => $this->oil->id, 'quantity' => '2.5']]])->assertJsonValidationErrors(['items.0.quantity']);
});

test('the shape of a return is validated', function () {
    $url = "/movements/{$this->sale->id}/return";

    $this->postJson($url, ['items' => []])->assertJsonValidationErrors(['items']);
    $this->postJson($url, ['items' => [['product_id' => $this->oil->id, 'quantity' => '0']]])->assertJsonValidationErrors(['items.0.quantity']);
    $this->postJson($url, ['items' => [['product_id' => $this->oil->id, 'quantity' => '1.2345']]])->assertJsonValidationErrors(['items.0.quantity']);
    $this->postJson($url, ['items' => [['product_id' => $this->boxes->id, 'quantity' => '1.5']]])->assertJsonValidationErrors(['items.0.quantity']);
    $this->postJson($url, ['items' => [['product_id' => $this->boxes->id, 'quantity' => '2']]])->assertOk();
});

test('a warehouse user returns only invoices of their own warehouse', function () {
    $ruralSale = ($this->sell)($this->rural, [['product_id' => $this->oil->id, 'quantity' => '1', 'unit_price' => '1000']]);

    $this->actingAs($this->city);
    $this->get("/movements/{$this->sale->id}/return")->assertOk();
    $this->get("/movements/{$ruralSale->id}/return")->assertNotFound();
    $this->postJson("/movements/{$ruralSale->id}/return", ['items' => [['product_id' => $this->oil->id, 'quantity' => '1']]])->assertNotFound();
    $this->get('/returns/create?type=sale_return')->assertSee('S-0001')->assertDontSee('S-0002');
});

test('a return cannot start from a return or from a cancelled invoice', function () {
    $return = app(MovementService::class)->return($this->owner, $this->sale, ['items' => [['product_id' => $this->oil->id, 'quantity' => '1']]]);

    $this->get("/movements/{$return->id}/return")->assertForbidden();
    $this->postJson("/movements/{$return->id}/return", ['items' => [['product_id' => $this->oil->id, 'quantity' => '1']]])->assertForbidden();

    $this->sale->update(['status' => MovementStatus::Cancelled]);
    $this->get("/movements/{$this->sale->id}/return")->assertForbidden();
});

test('the invoice details show returned quantities, the returns and the return button', function () {
    $return = app(MovementService::class)->return($this->owner, $this->sale, ['items' => [['product_id' => $this->oil->id, 'quantity' => '2']]]);

    $this->get("/movements/{$this->sale->id}")
        ->assertOk()
        ->assertSee('المرتجعات على هالفاتورة')
        ->assertSee('SR-0001')
        ->assertSee('1,800.00')
        ->assertSee('إنشاء مرتجع');

    $this->get("/movements/{$return->id}")
        ->assertOk()
        ->assertSee('الفاتورة الأصلية')
        ->assertSee(route('movements.show', $this->sale), false)
        ->assertDontSee('إنشاء مرتجع')
        ->assertDontSee('المرتجعات على هالفاتورة');

    $this->get("/movements/{$return->id}/print")->assertOk()->assertSee('مرتجع بيع')->assertSee('S-0001');
});

test('the return button disappears once everything was returned', function () {
    app(MovementService::class)->return($this->owner, $this->sale, ['items' => [
        ['product_id' => $this->oil->id, 'quantity' => '6'],
        ['product_id' => $this->boxes->id, 'quantity' => '40'],
    ]]);

    $this->get("/movements/{$this->sale->id}")->assertOk()->assertDontSee('إنشاء مرتجع');
});

test('the type tabs lead to the return screens', function () {
    $this->get('/movements/create?type=sale')
        ->assertOk()
        ->assertSee(route('returns.create', ['type' => 'purchase_return']), false)
        ->assertSee(route('returns.create', ['type' => 'sale_return']), false);
});
