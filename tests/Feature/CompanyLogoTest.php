<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Real temporary files with real magic bytes, because the validation must look at the content, not at the name.
 * (UploadedFile::fake() guesses the type from the name, and there is no GD extension for fake()->image().)
 */
function logoFile(string $kind, string $name): UploadedFile
{
    $content = match ($kind) {
        'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='),
        'jpg' => hex2bin('FFD8FFE000104A46494600010100000100010000').str_repeat("\0", 64).hex2bin('FFD9'),
        'webp' => 'RIFF'.pack('V', 26).'WEBPVP8 '.str_repeat("\0", 18),
        'svg' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="1" height="1"/></svg>',
        'text' => 'this is not an image',
        'big' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==').str_repeat("\0", 1100000),
    };

    $path = tempnam(sys_get_temp_dir(), 'logo');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

beforeEach(function () {
    Storage::fake('public');

    $this->company = Company::factory()->create();
    $this->owner = User::factory()->for($this->company)->create();
});

function saveSettings(array $overrides = []): Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->owner)->put('/settings', array_merge([
        'name' => test()->company->name,
        'primary_color' => '#1F4E79',
        'markup_percent' => '200',
    ], $overrides));
}

test('png, jpg and webp logos are accepted and kept in the company folder', function (string $kind, string $name) {
    saveSettings(['logo' => logoFile($kind, $name)])->assertSessionHasNoErrors();

    $path = $this->company->fresh()->logo_path;

    expect($path)->toStartWith("companies/{$this->company->id}/logo-")
        ->and($path)->toEndWith('.'.($kind === 'jpg' ? 'jpg' : $kind));
    Storage::disk('public')->assertExists($path);
})->with([
    'png' => ['png', 'logo.png'],
    'jpg' => ['jpg', 'logo.jpg'],
    'webp' => ['webp', 'logo.webp'],
]);

test('an svg logo is refused, even when it is named like a png', function (string $name) {
    saveSettings(['logo' => logoFile('svg', $name)])->assertSessionHasErrors('logo');

    expect($this->company->fresh()->logo_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
})->with(['logo.svg', 'logo.png']);

test('a file that is not an image is refused even with an image name', function () {
    saveSettings(['logo' => logoFile('text', 'logo.png')])->assertSessionHasErrors('logo');

    expect($this->company->fresh()->logo_path)->toBeNull();
});

test('a logo above one megabyte is refused', function () {
    saveSettings(['logo' => logoFile('big', 'logo.png')])->assertSessionHasErrors('logo');

    expect(session('errors')->first('logo'))->toContain('1 ميغا')
        ->and($this->company->fresh()->logo_path)->toBeNull();
});

test('replacing the logo deletes the old file', function () {
    saveSettings(['logo' => logoFile('png', 'first.png')]);
    $first = $this->company->fresh()->logo_path;

    saveSettings(['logo' => logoFile('webp', 'second.webp')]);
    $second = $this->company->fresh()->logo_path;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

test('saving without a file keeps the current logo', function () {
    saveSettings(['logo' => logoFile('png', 'logo.png')]);
    $path = $this->company->fresh()->logo_path;

    saveSettings(['name' => 'اسم جديد'])->assertSessionHasNoErrors();

    expect($this->company->fresh()->logo_path)->toBe($path);
    Storage::disk('public')->assertExists($path);
});

test('a refused save does not delete the current logo or keep the new file', function () {
    saveSettings(['logo' => logoFile('png', 'logo.png')]);
    $path = $this->company->fresh()->logo_path;

    saveSettings(['logo' => logoFile('webp', 'new.webp'), 'markup_percent' => '5'])->assertSessionHasErrors('markup_percent');

    expect($this->company->fresh()->logo_path)->toBe($path)
        ->and(Storage::disk('public')->allFiles())->toBe([$path]);
});

test('each company logo lives in its own folder', function () {
    $other = Company::factory()->create();
    $otherOwner = User::factory()->for($other)->create();

    saveSettings(['logo' => logoFile('png', 'logo.png')]);
    $this->actingAs($otherOwner)->put('/settings', [
        'name' => $other->name,
        'primary_color' => '#1F4E79',
        'markup_percent' => '200',
        'logo' => logoFile('png', 'logo.png'),
    ]);

    expect($this->company->fresh()->logo_path)->toStartWith("companies/{$this->company->id}/")
        ->and($other->fresh()->logo_path)->toStartWith("companies/{$other->id}/");
});

test('logoUrl is null without a logo and points at the storage link with one', function () {
    expect($this->company->logoUrl())->toBeNull();

    saveSettings(['logo' => logoFile('png', 'logo.png')]);
    $company = $this->company->fresh();

    expect($company->logoUrl())->toEndWith('/storage/'.$company->logo_path);
});

test('the top bar shows the logo instead of the first letter once there is one', function () {
    $this->actingAs($this->owner)->get('/dashboard')->assertDontSee('<img src', false);

    saveSettings(['logo' => logoFile('png', 'logo.png')]);

    $this->actingAs($this->owner)->get('/dashboard')->assertSee($this->company->fresh()->logoUrl(), false);
});
