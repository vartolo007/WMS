<?php

use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Movement;
use App\Models\MovementItem;
use App\Models\Operation;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * The review of the code against the SRS and the golden rules (docs/TRACEABILITY.md), kept as tests so it cannot rot.
 */

/**
 * @return list<SplFileInfo>
 */
function filesUnder(string $directory, string $extension): array
{
    return collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)))
        ->filter(fn (SplFileInfo $file) => $file->isFile() && str_ends_with($file->getFilename(), $extension))
        ->values()
        ->all();
}

/**
 * Files whose text matches the pattern, as paths relative to the project.
 *
 * @param  list<SplFileInfo>  $files
 * @return list<string>
 */
function filesMatching(array $files, string $pattern): array
{
    return collect($files)
        ->filter(fn (SplFileInfo $file) => preg_match($pattern, file_get_contents($file->getPathname())) === 1)
        ->map(fn (SplFileInfo $file) => str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()))
        ->values()
        ->all();
}

// --- SRS 5.9: indexes ---------------------------------------------------------------------------------------

test('every index of SRS 5.9 exists', function () {
    $columns = fn (string $table) => collect(Schema::getIndexes($table))->map(fn ($index) => $index['columns'])->all();

    $expected = [
        'users' => [['email']],
        'products' => [['company_id', 'barcode']],
        'movements' => [['company_id', 'type', 'number'], ['company_id', 'warehouse_id', 'date'], ['related_movement_id']],
        'operations' => [['company_id', 'warehouse_id', 'product_id', 'date'], ['company_id', 'date'], ['party_id', 'date'], ['movement_id']],
    ];

    $missing = [];

    foreach ($expected as $table => $indexes) {
        foreach ($indexes as $index) {
            if (! in_array($index, $columns($table), true)) {
                $missing[] = "{$table}(".implode(', ', $index).')';
            }
        }
    }

    expect($missing)->toBe([]);

    $unique = fn (string $table, array $cols) => collect(Schema::getIndexes($table))->contains(fn ($i) => $i['unique'] && $i['columns'] === $cols);

    expect($unique('users', ['email']))->toBeTrue()
        ->and($unique('products', ['company_id', 'barcode']))->toBeTrue()
        ->and($unique('movements', ['company_id', 'type', 'number']))->toBeTrue();
});

// --- SRS 4.1 / golden rule 7: tenant isolation ------------------------------------------------------------------

test('every tenant model carries the company scope, and the documented exceptions stay exceptions', function () {
    $without = collect([Product::class, Party::class, Movement::class, Operation::class])
        ->reject(fn (string $model) => in_array(BelongsToCompany::class, class_uses_recursive($model), true))
        ->values()
        ->all();

    expect($without)->toBe([]);

    // User: login looks users up before anyone is signed in (T13). MovementItem: always reached through its movement.
    expect(class_uses_recursive(User::class))->not->toContain(BelongsToCompany::class)
        ->and(class_uses_recursive(MovementItem::class))->not->toContain(BelongsToCompany::class)
        ->and(class_uses_recursive(Company::class))->not->toContain(BelongsToCompany::class);
});

// --- Golden rule 3 / SRS 4.2: no floats on money or quantities --------------------------------------------------

test('no float arithmetic or casts on money and quantities in PHP or JavaScript', function () {
    // Artisan output (the backup size in KB) is not money, so app/Console is outside this scan.
    $source = array_filter(filesUnder(app_path(), '.php'), fn (SplFileInfo $file) => ! str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Console'.DIRECTORY_SEPARATOR));

    $php = filesMatching($source, '/\(float\)|\(double\)|floatval\(|doubleval\(|[\'"](float|double|real)[\'"]|number_format\(/');

    expect($php)->toBe([]);

    // round() only on the integer percentage of the trial progress bar, never on money
    $rounding = filesMatching($source, '/\bround\(/');
    expect($rounding)->toBe(['app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'DashboardService.php']);

    expect(filesMatching(filesUnder(resource_path('js'), '.js'), '/parseFloat|toFixed|Math\.round/'))->toBe([]);
});

// --- Golden rule 10: colours come from CSS variables --------------------------------------------------------------

test('no hard-coded colour in any view', function () {
    expect(filesMatching(filesUnder(resource_path('views'), '.php'), '/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b(?![0-9a-fA-F])|\brgba?\(/'))->toBe([]);
});

// --- Golden rule 6: controllers are thin ----------------------------------------------------------------------------

test('controllers hold no money maths, transactions, locks or operation queries', function () {
    $offenders = filesMatching(filesUnder(app_path('Http/Controllers'), '.php'), '/\bMoney::|\bDB::|lockForUpdate|\bOperation::|\bPostingService\b/');

    expect($offenders)->toBe([]);
});

// --- SRS 4.4: security -------------------------------------------------------------------------------------------------

test('every form that posts carries a CSRF token and every fetch POST sends it', function () {
    $missing = collect(filesUnder(resource_path('views'), '.php'))
        ->filter(function (SplFileInfo $file) {
            $text = file_get_contents($file->getPathname());

            return preg_match_all('/<form[^>]*method="(POST|post)"/', $text) > substr_count($text, '@csrf');
        })
        ->map(fn (SplFileInfo $file) => $file->getFilename())
        ->values()
        ->all();

    expect($missing)->toBe([]);

    // the one JSON POST helper sends the token; no other script posts on its own
    expect(file_get_contents(resource_path('js/post-json.js')))->toContain('X-CSRF-TOKEN');
    expect(filesMatching(array_filter(filesUnder(resource_path('js'), '.js'), fn ($f) => $f->getFilename() !== 'post-json.js'), "/method:\s*['\"]POST/"))->toBe([]);
});

test('every signed-in route has a role guard, except the few every role may open', function () {
    $everyRole = ['dashboard', 'profile.edit', 'profile.update', 'subscription.suspended', 'logout',
        'password.confirm', 'password.update', 'verification.notice', 'verification.verify', 'verification.send', ''];

    $unguarded = collect(Route::getRoutes())
        ->filter(fn ($route) => in_array('auth', $route->gatherMiddleware(), true))
        ->reject(fn ($route) => collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'role:')))
        ->map(fn ($route) => (string) $route->getName())
        ->unique()
        ->diff($everyRole)
        ->values()
        ->all();

    expect($unguarded)->toBe([]);
});
