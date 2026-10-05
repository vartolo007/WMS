<?php

use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * No tenant table exists before Phase 2, so the trait is tested on a test-only table.
 * The table is TEMPORARY because CREATE/DROP TABLE would implicitly commit
 * the RefreshDatabase transaction in MySQL.
 */
class ScopedRecord extends Model
{
    use BelongsToCompany;

    protected $table = 'scoped_records_test';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('scoped_records_test', function (Blueprint $table) {
        $table->temporary();
        $table->id();
        $table->foreignId('company_id');
        $table->string('label');
        $table->timestamps();
    });

    $this->companyA = Company::factory()->create();
    $this->companyB = Company::factory()->create();

    ScopedRecord::withoutGlobalScopes()->insert([
        ['company_id' => $this->companyA->id, 'label' => 'A1'],
        ['company_id' => $this->companyA->id, 'label' => 'A2'],
        ['company_id' => $this->companyB->id, 'label' => 'B1'],
    ]);
});

afterEach(function () {
    DB::statement('DROP TEMPORARY TABLE IF EXISTS scoped_records_test');
});

test('a user sees only their company records', function () {
    $this->actingAs(User::factory()->for($this->companyA)->create());

    expect(ScopedRecord::pluck('label')->sort()->values()->all())->toBe(['A1', 'A2']);
});

test('a warehouse user is scoped to its company too', function () {
    $this->actingAs(User::factory()->warehouse()->for($this->companyB)->create());

    expect(ScopedRecord::pluck('label')->all())->toBe(['B1']);
});

test('a record of another company cannot be found by id', function () {
    $recordOfB = ScopedRecord::withoutGlobalScopes()->where('label', 'B1')->first();
    $this->actingAs(User::factory()->for($this->companyA)->create());

    expect(ScopedRecord::find($recordOfB->id))->toBeNull();
});

test('with no authenticated user nothing is returned', function () {
    expect(ScopedRecord::count())->toBe(0);
});

test('a super admin sees no tenant records', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    expect(ScopedRecord::count())->toBe(0);
});

test('creating fills company_id from the authenticated user', function () {
    $this->actingAs(User::factory()->for($this->companyB)->create());

    $record = ScopedRecord::create(['label' => 'B2']);

    expect($record->company_id)->toBe($this->companyB->id);
});
