<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Services\DashboardService;
use App\Support\CurrentWarehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Each role lands on its own dashboard (FR-04); the numbers come from DashboardService (FR-24).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboards, CurrentWarehouse $current): View|RedirectResponse
    {
        $user = $request->user();

        return match ($user->role) {
            // The companies page carries the platform statistics (FR-24), so it is the platform dashboard.
            Role::SuperAdmin => redirect()->route('admin.companies.index'),
            Role::Owner => $this->owner($request, $dashboards, $current),
            Role::Warehouse => view('dashboard.warehouse', $dashboards->forWarehouse($user)),
        };
    }

    private function owner(Request $request, DashboardService $dashboards, CurrentWarehouse $current): View
    {
        $user = $request->user();
        $warehouses = $user->company->warehouses()->orderBy('name')->get();

        return view('dashboard.owner', [
            'company' => $user->company,
            'hasWarehouses' => $warehouses->isNotEmpty(),
            'selected' => $current->get(),
            ...($warehouses->isEmpty() ? [] : $dashboards->forOwner($user, $warehouses, $current->get())),
        ]);
    }
}
