<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectWarehouseRequest;
use App\Support\CurrentWarehouse;
use Illuminate\Http\RedirectResponse;

/**
 * Owner picks the warehouse the screens work on (top bar selector).
 */
class WarehouseContextController extends Controller
{
    public function __invoke(SelectWarehouseRequest $request, CurrentWarehouse $current): RedirectResponse
    {
        $current->set($request->filled('warehouse_id') ? $request->integer('warehouse_id') : null);

        return back();
    }
}
