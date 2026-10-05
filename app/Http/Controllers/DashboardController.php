<?php

namespace App\Http\Controllers;

use App\Enums\CompanyStatus;
use App\Enums\Role;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Each role lands on its own dashboard (FR-04). Content is filled in Phase 5 (FR-24).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return match ($user->role) {
            Role::SuperAdmin => view('dashboard.admin', [
                'counts' => Company::toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
                'expiredTrials' => Company::where('status', CompanyStatus::Trial)->whereDate('trial_ends_at', '<', today())->count(),
            ]),
            Role::Owner => view('dashboard.owner', [
                'company' => $user->company,
                'hasWarehouses' => $user->company->warehouses()->exists(),
            ]),
            Role::Warehouse => view('dashboard.warehouse'),
        };
    }
}
