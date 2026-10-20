<?php

namespace App\Http\Controllers;

use App\Models\Movement;
use App\Services\MovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Cancel a movement (FR-17). Never an edit or a delete: the movement stays, marked cancelled.
 * A refused cancel (active return, negative balance) comes back as a validation error on the `cancel` key.
 */
class MovementCancelController extends Controller
{
    public function __invoke(Request $request, Movement $movement, MovementService $movements): RedirectResponse
    {
        Gate::authorize('cancel', $movement);

        $cancelled = $movements->cancel($request->user(), $movement);

        return back()->with('status', 'تم إلغاء الحركة '.$cancelled->displayNumber().'.');
    }
}
