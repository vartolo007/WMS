<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WarehouseContextController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

// No landing page in the design: go straight to the dashboard or the login.
Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'subscription'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // FR-26. No account deletion: nothing is hard-deleted (T12).
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Q2: the catalogues (products, parties) are managed by the owner and by warehouse users (CatalogPolicy decides).
    Route::middleware('role:owner,warehouse')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');

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
