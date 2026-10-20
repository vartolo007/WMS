<?php

namespace App\Providers;

use App\Enums\ReportKind;
use App\Policies\ReportPolicy;
use App\Support\CurrentWarehouse;
use App\Support\Navigation;
use Illuminate\Support\Facades\Gate;
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
        // Reports are an enum, not a model, so its policy is registered by hand.
        Gate::policy(ReportKind::class, ReportPolicy::class);

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
