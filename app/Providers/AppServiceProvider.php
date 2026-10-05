<?php

namespace App\Providers;

use App\Support\CurrentWarehouse;
use App\Support\Navigation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentWarehouse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Data for the app shell (design batch 1): sidebar items, warehouse selector.
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            $view->with([
                'navigation' => Navigation::for($user),
                'currentWarehouse' => app(CurrentWarehouse::class)->get(),
                'warehouseOptions' => $user->isOwner()
                    ? $user->company->warehouses()->orderBy('name')->get(['id', 'name', 'is_active'])
                    : collect(),
            ]);
        });
    }
}
