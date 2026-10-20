<?php

namespace App\Http\Controllers;

use App\Enums\PartyType;
use App\Http\Requests\StorePartyRequest;
use App\Http\Requests\UpdatePartyRequest;
use App\Models\Party;
use App\Services\PartyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Customers and suppliers, one shared list per company (FR-08). No delete: deactivate instead (T12).
 */
class PartyController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Party::class);

        $type = PartyType::tryFrom((string) $request->query('type'));

        $parties = Party::query()
            ->ofType($type)
            ->search($request->query('q'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('parties.index', [
            'parties' => $parties,
            'type' => $type,
            'search' => (string) $request->query('q'),
        ]);
    }

    public function store(StorePartyRequest $request): JsonResponse|RedirectResponse
    {
        $party = Party::create($request->validated());

        // The invoice screen adds a missing supplier / customer without leaving the invoice.
        if ($request->wantsJson()) {
            return response()->json(['id' => $party->id, 'name' => $party->name, 'type' => $party->type->value], 201);
        }

        return redirect()->route('parties.index')->with('status', 'تمت إضافة الطرف.');
    }

    public function update(UpdatePartyRequest $request, Party $party, PartyService $parties): RedirectResponse
    {
        $parties->update($party, $request->validated());

        return redirect()->route('parties.index')->with('status', 'تم حفظ الطرف.');
    }

    public function toggle(Party $party): RedirectResponse
    {
        Gate::authorize('update', $party);

        $party->update(['is_active' => ! $party->is_active]);

        return back()->with('status', $party->is_active ? 'تم تفعيل الطرف.' : 'تم إيقاف الطرف.');
    }
}
