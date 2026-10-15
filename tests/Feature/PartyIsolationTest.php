<?php

use App\Models\Company;
use App\Models\Party;
use App\Models\User;

beforeEach(function () {
    $this->companyA = Company::factory()->create();
    $this->companyB = Company::factory()->create();
    $this->ownerA = User::factory()->for($this->companyA)->create();

    $this->partyA = Party::factory()->for($this->companyA)->create(['name' => 'طرف أ']);
    $this->partyB = Party::factory()->for($this->companyB)->create(['name' => 'طرف ب']);
});

test('a user sees only the parties of their company', function () {
    $this->actingAs($this->ownerA);

    expect(Party::pluck('name')->all())->toBe(['طرف أ']);
});

test('a party of another company cannot be found by id', function () {
    $this->actingAs($this->ownerA);

    expect(Party::find($this->partyB->id))->toBeNull();
});

test('with no authenticated user no party is returned', function () {
    expect(Party::count())->toBe(0);
});

test('the list page shows only the own company parties', function () {
    $this->actingAs($this->ownerA)
        ->get('/parties')
        ->assertOk()
        ->assertSee('طرف أ')
        ->assertDontSee('طرف ب');
});

test('a party of another company cannot be edited or toggled', function () {
    $this->actingAs($this->ownerA);

    $this->put("/parties/{$this->partyB->id}", ['name' => 'مخترق', 'type' => 'customer'])->assertNotFound();
    $this->patch("/parties/{$this->partyB->id}/toggle")->assertNotFound();

    $partyB = Party::withoutGlobalScopes()->find($this->partyB->id);
    expect($partyB->name)->toBe('طرف ب')
        ->and($partyB->is_active)->toBeTrue();
});

test('company_id comes from the logged-in user, never from the request', function () {
    $this->actingAs($this->ownerA)->post('/parties', [
        'name' => 'طرف جديد',
        'type' => 'supplier',
        'company_id' => $this->companyB->id,
    ])->assertRedirect(route('parties.index'));

    $created = Party::withoutGlobalScopes()->where('name', 'طرف جديد')->sole();
    expect($created->company_id)->toBe($this->companyA->id);
});
