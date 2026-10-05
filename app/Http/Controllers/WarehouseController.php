<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Models\User;
use App\Services\WarehouseAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Owner manages the company's warehouse accounts (FR-05). No delete: deactivate instead.
 */
class WarehouseController extends Controller
{
    public function __construct(private WarehouseAccountService $accounts) {}

    public function index(Request $request): View
    {
        Gate::authorize('manageWarehouses', User::class);

        $warehouses = $request->user()->company->warehouses()->orderBy('name')->get();

        return view('warehouses.index', compact('warehouses'));
    }

    public function create(): View
    {
        Gate::authorize('manageWarehouses', User::class);

        return view('warehouses.create');
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $this->accounts->create($request->user()->company, $request->validated());

        return redirect()->route('warehouses.index')->with('status', 'تم إنشاء المستودع.');
    }

    public function edit(User $warehouse): View
    {
        Gate::authorize('update', $warehouse);

        return view('warehouses.edit', compact('warehouse'));
    }

    public function update(UpdateWarehouseRequest $request, User $warehouse): RedirectResponse
    {
        $this->accounts->update($warehouse, $request->validated());

        return redirect()->route('warehouses.index')->with('status', 'تم حفظ المستودع.');
    }

    public function toggle(User $warehouse): RedirectResponse
    {
        Gate::authorize('update', $warehouse);

        $this->accounts->setActive($warehouse, ! $warehouse->is_active);

        return back()->with('status', $warehouse->is_active ? 'تم تفعيل المستودع.' : 'تم إيقاف المستودع.');
    }
}
