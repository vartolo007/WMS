<?php

namespace App\Http\Controllers;

use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Http\Requests\StoreReturnRequest;
use App\Models\Movement;
use App\Services\MovementService;
use App\Services\ReturnService;
use App\Services\StockService;
use App\Support\CurrentWarehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Purchase returns and sale returns (FR-15): find the original invoice, then return part of it.
 * Party, warehouse and prices come from the original; nothing about them is asked again.
 */
class MovementReturnController extends Controller
{
    /**
     * Step 1: search the original invoice by its number, document number or party.
     */
    public function search(Request $request, CurrentWarehouse $current, ReturnService $returns): View
    {
        Gate::authorize('create', Movement::class);

        $type = MovementType::tryFrom((string) $request->query('type', MovementType::SaleReturn->value));
        abort_unless($type?->isReturn(), 404);

        $term = trim((string) $request->query('q'));

        $invoices = Movement::query()
            ->with(['party', 'warehouse'])
            ->where('type', $type->originalType())
            ->where('status', MovementStatus::Confirmed)
            ->filter(['q' => $term], $request->user()->isOwner() ? $current->id() : null)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('returns.search', [
            'type' => $type,
            'term' => $term,
            'invoices' => $invoices,
            'returnable' => $invoices->mapWithKeys(fn (Movement $invoice) => [$invoice->id => $returns->isReturnable($invoice)]),
        ]);
    }

    /**
     * Step 2: the original invoice's lines with the quantity still returnable.
     */
    public function create(Movement $movement, ReturnService $returns, StockService $stock): View|RedirectResponse
    {
        Gate::authorize('createReturn', $movement);

        if (! $returns->isReturnable($movement)) {
            return redirect()->route('movements.show', $movement)->with('status', 'ما في كميات متاحة للإرجاع من هالفاتورة.');
        }

        $type = $movement->type->returnType();
        $lines = $returns->lines($movement);

        return view('movements.return', [
            'original' => $movement->load(['party', 'warehouse']),
            'type' => $type,
            'lines' => $lines,
            // Only a purchase return takes stock out, so only it shows (and checks) the current balance.
            'balances' => $type->reducesSourceStock() ? $stock->balances($lines->keys()->all(), $movement->warehouse_id) : [],
        ]);
    }

    public function store(StoreReturnRequest $request, Movement $movement, MovementService $movements): JsonResponse|RedirectResponse
    {
        $return = $movements->return($request->user(), $movement, $request->validated());

        $target = route('movements.show', $return);
        session()->flash('status', 'تم حفظ الحركة '.$return->displayNumber().'.');

        return $request->wantsJson() ? response()->json(['redirect' => $target]) : redirect($target);
    }
}
