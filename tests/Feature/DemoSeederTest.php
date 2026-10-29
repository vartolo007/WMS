<?php

use App\Enums\StockFilter;
use App\Models\Company;
use App\Models\Movement;
use App\Models\User;
use App\Services\ReportService;
use App\Support\ReportFilters;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Auth;

test('the demo data posts a week of movements through the services and leaves nobody signed in', function () {
    $this->seed(DemoSeeder::class);

    $company = Company::where('name', 'شركة النور')->firstOrFail();
    $movements = Movement::withoutGlobalScopes()->where('company_id', $company->id)->orderBy('id')->get();

    expect(Auth::check())->toBeFalse()
        ->and($movements->pluck('type')->map->value->all())->toBe(['purchase', 'purchase', 'sale', 'sale_return', 'transfer', 'sale', 'sale'])
        ->and($movements->pluck('total')->all())->toBe(['237000.00', '52300.00', '15600.00', '2836.36', '21000.00', '3900.00', '33000.00']);

    $this->actingAs(User::where('email', 'owner@demo.test')->firstOrFail());
    $low = app(ReportService::class)->stock(new ReportFilters(splitByWarehouse: true, stockFilter: StockFilter::BelowMin));

    expect($low->map(fn ($row) => $row['product']->name.'@'.$row['warehouse']->name)->all())->toBe(['سكر@مستودع الريف']);
});
