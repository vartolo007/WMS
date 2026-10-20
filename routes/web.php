<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\MovementReturnController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductLookupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SuspendedSubscriptionController;
use App\Http\Controllers\WarehouseContextController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

// No landing page in the design: go straight to the dashboard or the login.
Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'subscription'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // FR-03: the only page of a suspended company's users (`subscription` lets it through).
    Route::get('/subscription/suspended', SuspendedSubscriptionController::class)->name('subscription.suspended');

    // FR-26. No account deletion: nothing is hard-deleted (T12).
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Q2: the catalogues (products, parties) are managed by the owner and by warehouse users (CatalogPolicy decides).
    Route::middleware('role:owner,warehouse')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');

        // FR-10 → FR-14: purchase and sale invoices. Saved movements are never edited or deleted.
        Route::get('/products/lookup', ProductLookupController::class)->name('products.lookup');
        Route::get('/movements', [MovementController::class, 'index'])->name('movements.index');
        Route::get('/movements/create', [MovementController::class, 'create'])->name('movements.create');
        Route::post('/movements', [MovementController::class, 'store'])->name('movements.store');
        Route::get('/movements/{movement}', [MovementController::class, 'show'])->name('movements.show');
        Route::get('/movements/{movement}/print', [MovementController::class, 'print'])->name('movements.print');

        // FR-15: a return starts from its original invoice (search it, then the return form).
        Route::get('/returns/create', [MovementReturnController::class, 'search'])->name('returns.create');
        Route::get('/movements/{movement}/return', [MovementReturnController::class, 'create'])->name('movements.return');
        Route::post('/movements/{movement}/return', [MovementReturnController::class, 'store'])->name('movements.return.store');

        // FR-19 → FR-23: the reports centre, one engine, 14 reports. Read-only.
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('/reports/{report}/print', [ReportController::class, 'print'])->name('reports.print');

        Route::get('/parties', [PartyController::class, 'index'])->name('parties.index');
        Route::post('/parties', [PartyController::class, 'store'])->name('parties.store');
        Route::put('/parties/{party}', [PartyController::class, 'update'])->name('parties.update');
        Route::patch('/parties/{party}/toggle', [PartyController::class, 'toggle'])->name('parties.toggle');
    });

    Route::middleware('role:owner')->group(function () {
        Route::post('/warehouse-context', WarehouseContextController::class)->name('warehouse-context');

        Route::get('/settings', [CompanySettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [CompanySettingsController::class, 'update'])->name('settings.update');

        Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::get('/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
        Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
        Route::patch('/warehouses/{warehouse}/toggle', [WarehouseController::class, 'toggle'])->name('warehouses.toggle');
    });

    Route::middleware('role:super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::patch('/companies/{company}/activate', [CompanyController::class, 'activate'])->name('companies.activate');
        Route::patch('/companies/{company}/suspend', [CompanyController::class, 'suspend'])->name('companies.suspend');
        Route::patch('/companies/{company}/extend-trial', [CompanyController::class, 'extendTrial'])->name('companies.extend-trial');
    });
});

require __DIR__.'/auth.php';
