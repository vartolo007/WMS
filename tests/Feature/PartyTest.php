<?php

use App\Enums\PartyType;
use App\Models\Company;
use App\Models\Party;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->owner = User::factory()->for($this->company)->create();
});

function partyData(array $overrides = []): array
{
    return array_merge(['name' => 'محمد الأحمد', 'type' => 'customer'], $overrides);
}

// --- Who may manage the parties (Q2) -----------------------------------------------------------

test('the owner and a warehouse user can both add and edit parties', function (string $role) {
    $user = $role === 'owner' ? $this->owner : User::factory()->warehouse()->for($this->company)->create();

    $this->actingAs($user)->post('/parties', partyData())->assertRedirect(route('parties.index'));
    $party = Party::sole();

    $this->actingAs($user)->put("/parties/{$party->id}", partyData(['name' => 'محمد الخطيب']))
        ->assertRedirect(route('parties.index'));

    expect($party->fresh()->name)->toBe('محمد الخطيب');
})->with(['owner', 'warehouse']);

test('the platform admin has no access to the parties', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/parties')
        ->assertForbidden();
});

test('a guest is sent to login', function () {
    $this->get('/parties')->assertRedirect('/login');
});

test('products and parties share one permission rule', function () {
    $warehouse = User::factory()->warehouse()->for($this->company)->create();
    $product = App\Models\Product::factory()->for($this->company)->create();
    $party = Party::factory()->for($this->company)->create();

    expect($warehouse->can('update', $product))->toBe($warehouse->can('update', $party))
        ->and($this->owner->can('create', Party::class))->toBeTrue()
        ->and(User::factory()->superAdmin()->create()->can('create', Party::class))->toBeFalse();
});

// --- Fields and validation ---------------------------------------------------------------------

test('a party is created with all its fields', function () {
    $this->actingAs($this->owner)->post('/parties', partyData([
        'phone' => '+963 944 111 222',
        'type' => 'supplier',
    ]))->assertRedirect(route('parties.index'));

    $party = Party::sole();
    expect($party->name)->toBe('محمد الأحمد')
        ->and($party->phone)->toBe('+963 944 111 222')
        ->and($party->type)->toBe(PartyType::Supplier)
        ->and($party->is_active)->toBeTrue();
});

test('name and type are required, the type must be customer or supplier', function () {
    $this->actingAs($this->owner);

    $this->post('/parties', ['name' => '', 'type' => ''])->assertSessionHasErrors(['name', 'type']);
    $this->post('/parties', partyData(['type' => 'partner']))->assertSessionHasErrors('type');

    expect(Party::count())->toBe(0);
});

test('the phone is optional and must look like a phone number', function () {
    $this->actingAs($this->owner);

    $this->post('/parties', partyData(['phone' => '']))->assertSessionHasNoErrors();
    $this->post('/parties', partyData(['phone' => '0944-111 222', 'name' => 'ب']))->assertSessionHasNoErrors();
    $this->post('/parties', partyData(['phone' => '٠٩٤٤ ١١١ ٢٢٢', 'name' => 'ج']))->assertSessionHasNoErrors();
    $this->post('/parties', partyData(['phone' => 'call me', 'name' => 'د']))->assertSessionHasErrors('phone');

    expect(Party::count())->toBe(3)
        ->and(Party::whereNull('phone')->count())->toBe(1);
});

test('two parties may share a name', function () {
    $this->actingAs($this->owner);

    $this->post('/parties', partyData());
    $this->post('/parties', partyData())->assertSessionHasNoErrors();

    expect(Party::count())->toBe(2);
});

// --- Deactivate instead of delete --------------------------------------------------------------

test('a party is deactivated and reactivated, never deleted', function () {
    $party = Party::factory()->for($this->company)->create();
    $this->actingAs($this->owner);

    $this->patch("/parties/{$party->id}/toggle");
    expect($party->fresh()->is_active)->toBeFalse();

    $this->patch("/parties/{$party->id}/toggle");
    expect($party->fresh()->is_active)->toBeTrue();

    $this->delete("/parties/{$party->id}")->assertStatus(405);
    expect(Party::count())->toBe(1);
});

// --- List, tabs and search ---------------------------------------------------------------------

test('the list shows inactive parties with a stopped badge', function () {
    Party::factory()->for($this->company)->create(['name' => 'عميل فعال']);
    Party::factory()->for($this->company)->inactive()->create(['name' => 'عميل متوقف']);

    $this->actingAs($this->owner)->get('/parties')
        ->assertSee('عميل فعال')
        ->assertSee('عميل متوقف')
        ->assertSee('موقوف');
});

test('the tabs filter by type', function () {
    Party::factory()->for($this->company)->create(['name' => 'زبون الدكان']);
    Party::factory()->for($this->company)->supplier()->create(['name' => 'تاجر الجملة']);
    $this->actingAs($this->owner);

    $this->get('/parties')->assertSee('زبون الدكان')->assertSee('تاجر الجملة');
    $this->get('/parties?type=customer')->assertSee('زبون الدكان')->assertDontSee('تاجر الجملة');
    $this->get('/parties?type=supplier')->assertSee('تاجر الجملة')->assertDontSee('زبون الدكان');
    $this->get('/parties?type=nonsense')->assertSee('زبون الدكان')->assertSee('تاجر الجملة');
});

test('search matches part of the name or part of the phone', function () {
    Party::factory()->for($this->company)->create(['name' => 'زبون الدكان', 'phone' => '0944 111 222']);
    Party::factory()->for($this->company)->create(['name' => 'تاجر الجملة', 'phone' => '0933 555 666']);
    $this->actingAs($this->owner);

    $this->get('/parties?q=الدكان')->assertSee('زبون الدكان')->assertDontSee('تاجر الجملة');
    $this->get('/parties?q=555')->assertSee('تاجر الجملة')->assertDontSee('زبون الدكان');
});

test('wildcards typed in the search box are plain text', function () {
    Party::factory()->for($this->company)->create(['name' => 'زبون الدكان']);
    $this->actingAs($this->owner);

    $this->get('/parties?q=%25')->assertDontSee('زبون الدكان');
    $this->get('/parties?q=_')->assertDontSee('زبون الدكان');
});

test('an empty result tells the user so', function () {
    $this->actingAs($this->owner)
        ->get('/parties?q=غير-موجود')
        ->assertSee('ما في نتائج');
});

test('the sidebar links to the parties for the owner and the warehouse', function () {
    $this->actingAs($this->owner)->get('/dashboard')->assertSee(route('parties.index'));

    $warehouse = User::factory()->warehouse()->for($this->company)->create();
    $this->actingAs($warehouse)->get('/dashboard')->assertSee(route('parties.index'));
});
