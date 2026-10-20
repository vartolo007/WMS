<?php

namespace App\Http\Controllers;

use App\Enums\ReportKind;
use App\Http\Requests\ReportFilterRequest;
use App\Models\Party;
use App\Models\Product;
use App\Services\ReportService;
use App\Support\ReportFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The reports centre (screens 16–17) and the print copy (screen 18). Every report is the same engine
 * with the preset of its `ReportKind`; read-only.
 */
class ReportController extends Controller
{
    /** Rows per page on screen; the print copy takes everything up to this many. */
    private const PER_PAGE = 50;

    private const PRINT_LIMIT = 5000;

    public function index(): RedirectResponse
    {
        Gate::authorize('viewAny', ReportKind::class);

        return redirect()->route('reports.show', ReportKind::DailySales);
    }

    public function show(ReportKind $report, ReportFilterRequest $request, ReportService $reports): View
    {
        return view('reports.show', $this->data($report, $request, $reports, print: false));
    }

    public function print(ReportKind $report, ReportFilterRequest $request, ReportService $reports): View
    {
        return view('reports.print', $this->data($report, $request, $reports, print: true));
    }

    /**
     * @return array<string, mixed>
     */
    private function data(ReportKind $report, ReportFilterRequest $request, ReportService $reports, bool $print): array
    {
        $user = $request->user();
        $filters = $report->filters($request->validated(), $user);

        $product = $filters->productId ? Product::query()->find($filters->productId) : null;
        $party = $filters->partyId ? Party::query()->find($filters->partyId) : null;

        $warehouses = $user->isOwner()
            ? $user->company->warehouses()->orderBy('name')->get(['id', 'name', 'is_active'])
            : collect([$user]);

        $chosen = $filters->warehouseId ? $warehouses->firstWhere('id', $filters->warehouseId) : null;

        // A report that needs one product / party shows a prompt until it is chosen.
        $missing = match (true) {
            $report->needsProduct() && $product === null => 'product',
            $report->needsParty() && $party === null => 'party',
            default => null,
        };

        return [
            'report' => $report,
            'filters' => $filters,
            'product' => $product,
            'party' => $party,
            'missing' => $missing,
            'warehouses' => $warehouses,
            'warehouseName' => $chosen?->name ?? 'كل المستودعات',
            'products' => $report->needsProduct() ? Product::query()->orderBy('name')->get(['id', 'name', 'unit']) : collect(),
            'parties' => $report->needsParty() ? Party::query()->ofType($report->partyType())->orderBy('name')->get(['id', 'name', 'type']) : collect(),
            'result' => $missing === null ? $this->result($report, $filters, $warehouses, $request, $reports, $print) : null,
            'print' => $print,
        ];
    }

    /**
     * @param  Collection<int, \App\Models\User>  $warehouses
     * @return array<string, mixed>
     */
    private function result(ReportKind $report, ReportFilters $filters, Collection $warehouses, ReportFilterRequest $request, ReportService $reports, bool $print): array
    {
        $perPage = $print ? self::PRINT_LIMIT : self::PER_PAGE;

        return match ($report->view()) {
            'daily-sales' => $reports->dailySales($filters->dateTo, $this->salesWarehouses($filters, $warehouses)),
            'summary' => $reports->summary($filters),
            'ledger' => $reports->ledger($filters, $print ? self::PRINT_LIMIT : 100),
            'party' => [
                'summary' => $reports->partySummary($filters),
                'rows' => $reports->details($filters->with(types: []), $perPage),
            ],
            'stock' => [
                'rows' => $reports->paginate($reports->stock($filters), $print ? self::PRINT_LIMIT : $perPage, (int) $request->input('page', 1)),
            ],
            default => [
                'rows' => $reports->details($filters, $perPage),
                'totals' => $reports->typeTotals($filters),
            ],
        };
    }

    /**
     * The warehouses of the daily sales report: the chosen one, otherwise every active warehouse.
     *
     * @param  Collection<int, \App\Models\User>  $warehouses
     * @return Collection<int, \App\Models\User>
     */
    private function salesWarehouses(ReportFilters $filters, Collection $warehouses): Collection
    {
        return $filters->warehouseId
            ? $warehouses->where('id', $filters->warehouseId)->values()
            : $warehouses->filter(fn ($warehouse) => $warehouse->is_active)->values();
    }
}
