<?php

namespace App\Http\Controllers;

use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Http\Requests\MovementFilterRequest;
use App\Http\Requests\StoreMovementRequest;
use App\Models\Movement;
use App\Models\Party;
use App\Services\MovementService;
use App\Services\ReturnService;
use App\Support\CurrentWarehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Purchase and sale invoices: list, new invoice, details, print (FR-10 → FR-14).
 * No edit and no delete: a saved movement never changes (golden rule 9).
 */
class MovementController extends Controller
{
    /** The types this screen creates; returns have their own screens (MovementReturnController). */
    private const CREATABLE = [MovementType::Purchase, MovementType::Sale, MovementType::Transfer];

    public function index(MovementFilterRequest $request, CurrentWarehouse $current): View
    {
        $user = $request->user();
        $filters = $request->validated();

        // The owner's list follows the top-bar warehouse until the filter on the page says otherwise.
        $warehouseId = $user->isOwner()
            ? ($request->has('warehouse_id') ? ($filters['warehouse_id'] ?? null) : $current->id())
            : null;

        $movements = Movement::query()
            ->with(['party', 'warehouse', 'targetWarehouse', 'items.product'])
            ->withCount('items')
            ->withExists(['returns as has_active_return' => fn ($query) => $query->where('status', MovementStatus::Confirmed)])
            ->filter($filters, $warehouseId)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('movements.index', [
            'movements' => $movements,
            'filters' => $filters,
            'warehouseId' => $warehouseId,
            'warehouses' => $user->isOwner() ? $user->company->warehouses()->orderBy('name')->get(['id', 'name']) : collect(),
            'filtered' => collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty() || ($user->isOwner() && $warehouseId !== null),
        ]);
    }

    public function create(Request $request, CurrentWarehouse $current): View
    {
        Gate::authorize('create', Movement::class);

        $type = MovementType::tryFrom((string) $request->query('type', MovementType::Sale->value));
        abort_unless(in_array($type, self::CREATABLE, true), 404);

        $user = $request->user();

        return view('movements.create', [
            'type' => $type,
            'parties' => $type->partyType() === null ? collect() : Party::query()->active()->ofType($type->partyType())->orderBy('name')->get(['id', 'name']),
            'warehouses' => $user->isOwner()
                ? $user->company->warehouses()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect([$user]),
            'warehouseId' => $current->id(),
            // Every active warehouse of the company: the receiving side of a transfer, and the names shown in its summary.
            'targetWarehouses' => $user->company->warehouses()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreMovementRequest $request, MovementService $movements): JsonResponse|RedirectResponse
    {
        $movement = match ($request->movementType()) {
            MovementType::Purchase => $movements->purchase($request->user(), $request->movementData()),
            MovementType::Sale => $movements->sale($request->user(), $request->movementData()),
            MovementType::Transfer => $movements->transfer($request->user(), $request->movementData()),
        };

        $target = route('movements.show', $movement);
        session()->flash('status', 'تم حفظ الحركة '.$movement->displayNumber().'.');

        // The invoice screen posts JSON so a rejected save keeps every line on screen.
        return $request->wantsJson() ? response()->json(['redirect' => $target]) : redirect($target);
    }

    public function show(Movement $movement, ReturnService $returns): View
    {
        Gate::authorize('view', $movement);

        $movement = $this->loaded($movement);
        $isInvoice = $movement->type->returnType() !== null;
        $returnLines = $isInvoice ? $returns->lines($movement) : collect();
        $returnList = $isInvoice ? $movement->returns()->orderBy('date')->orderBy('id')->get() : collect();
        $hasActiveReturn = $returnList->contains(fn (Movement $return) => ! $return->isCancelled());

        return view('movements.show', [
            'movement' => $movement,
            // An invoice shows what was returned of each product, and the returns themselves.
            'returnLines' => $returnLines,
            'returns' => $returnList,
            // An invoice with an active return waits for that return to be cancelled first (SRS 3.4.8).
            'canCancel' => Gate::allows('cancel', $movement) && ! $hasActiveReturn,
            'cancelBlockedByReturn' => Gate::allows('cancel', $movement) && $hasActiveReturn,
            'canReturn' => $isInvoice
                && Gate::allows('createReturn', $movement)
                && $returns->anyReturnable($returnLines),
        ]);
    }

    public function print(Movement $movement): View
    {
        Gate::authorize('view', $movement);

        return view('movements.print', ['movement' => $this->loaded($movement)]);
    }

    private function loaded(Movement $movement): Movement
    {
        return $movement->load(['items.product', 'party', 'warehouse', 'targetWarehouse', 'creator', 'company', 'relatedMovement', 'canceller']);
    }
}
