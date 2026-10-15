<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Each role lands on its own dashboard (FR-04). Content is filled in Phase 5 (FR-24).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        return match ($user->role) {
            // The companies page carries the platform statistics (FR-24), so it is the platform dashboard.
            Role::SuperAdmin => redirect()->route('admin.companies.index'),
            Role::Owner => view('dashboard.owner', [
                'company' => $user->company,
                'hasWarehouses' => $user->company->warehouses()->exists(),
            ]),
            Role::Warehouse => view('dashboard.warehouse'),
        };
    }
}
