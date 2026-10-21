<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\Role;
use App\Enums\StockFilter;
use App\Models\Operation;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use App\Support\ReportFilters;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as PaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The one report engine (FR-19). Read-only: every quantity and amount comes from `operations`
 * (golden rule 2), money is `net_value`, nothing is stored (rule 8). Company and warehouse isolation
 * come from the global scopes of `Operation`. Other tables are joined for labels only (numbers, names).
 * SQL DECIMAL aggregates stay strings; subtraction goes through `Money` (rule 3).
 */
class ReportService
{
    private const AGGREGATES = 'COUNT(DISTINCT operations.movement_id) AS movements,
        COALESCE(SUM(operations.qty_in), 0) AS qty_in,
        COALESCE(SUM(operations.qty_out), 0) AS qty_out,
        COALESCE(SUM(operations.value), 0) AS gross_value,
        COALESCE(SUM(operations.net_value), 0) AS net_value';

    // ---- summary -----------------------------------------------------------------------------------

    /**
     * Totals grouped by product, party or warehouse (`$filters->groupBy`).
     * Quantities of different products cannot be added, so the totals row has no quantities.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array{movements: int, gross_value: string, net_value: string}}
     */
    public function summary(ReportFilters $filters): array
    {
        $query = $this->base($filters);

        [$key, $label, $extra] = match ($filters->groupBy) {
            'party' => ['operations.party_id', 'pa.name', null],
            'warehouse' => ['operations.warehouse_id', 'w.name', null],
            default => ['operations.product_id', 'p.name', 'p.unit'],
        };

        match ($filters->groupBy) {
            'party' => $query->leftJoin('parties as pa', 'pa.id', '=', 'operations.party_id'),
            'warehouse' => $query->join('users as w', 'w.id', '=', 'operations.warehouse_id'),
            default => $query->join('products as p', 'p.id', '=', 'operations.product_id'),
        };

        $groups = array_filter([$key, $label, $extra]);

        $rows = $query->groupBy($groups)
            ->orderBy($label)
            ->selectRaw(implode(', ', $groups).', '.self::AGGREGATES.', '.$key.' AS group_id, '.$label.' AS group_label')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->group_id,
                'label' => $row->group_label,
                'unit' => $filters->groupBy === 'product' ? $row->unit : null,
                'movements' => (int) $row->movements,
                'qty_in' => Money::quantity((string) $row->qty_in),
                'qty_out' => Money::quantity((string) $row->qty_out),
                'gross_value' => Money::money((string) $row->gross_value),
                'net_value' => Money::money((string) $row->net_value),
            ]);

        return [
            'rows' => $rows,
            'totals' => [
                'movements' => (int) $this->base($filters)->distinct()->count('operations.movement_id'),
                'gross_value' => Money::sum($rows->pluck('gross_value')),
                'net_value' => Money::sum($rows->pluck('net_value')),
            ],
        ];
    }

    // ---- details -----------------------------------------------------------------------------------

    /**
     * Every operation row with its movement, oldest first. A transfer shows as an out row in the source
     * and an in row in the target (SRS 3.4.7).
     */
    public function details(ReportFilters $filters, int $perPage = 50): PaginatorContract
    {
        return $this->labelled($this->base($filters))
            ->orderBy('operations.date')
            ->orderBy('operations.id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => $this->detailRow($row));
    }

    /**
     * Movement count and net value per movement type, for the footer of a details report.
     *
     * @return array<string, array{movements: int, net_value: string}> keyed by type value
     */
    public function typeTotals(ReportFilters $filters): array
    {
        return $this->base($filters)
            ->groupBy('operations.type')
            ->selectRaw('operations.type AS type, COUNT(DISTINCT operations.movement_id) AS movements, COALESCE(SUM(operations.net_value), 0) AS net_value')
            ->get()
            ->mapWithKeys(fn ($row) => [($row->type instanceof MovementType ? $row->type->value : $row->type) => ['movements' => (int) $row->movements, 'net_value' => Money::money((string) $row->net_value)]])
            ->all();
    }

    // ---- ledger ------------------------------------------------------------------------------------

    /**
     * A product's movements in time order with a running balance, starting from the balance before the
     * period (SRS 3.6.4). `types` is ignored: a ledger always shows everything that moved the stock.
     *
     * @return array{opening: string, closing: string, rows: PaginatorContract, in_qty: string, out_qty: string, in_value: string, out_value: string}
     */
    public function ledger(ReportFilters $filters, int $perPage = 100): array
    {
        $filters = $filters->with(types: []);

        $opening = $filters->dateFrom === null ? Money::quantity('0') : $this->balanceBefore($filters, $filters->dateFrom);

        $rows = $this->labelled($this->base($filters))
            ->selectRaw('SUM(operations.qty_in - operations.qty_out) OVER (ORDER BY operations.date, operations.id) AS running')
            ->orderBy('operations.date')
            ->orderBy('operations.id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => $this->detailRow($row) + ['running' => Money::sumQuantity([$opening, (string) $row->running])]);

        // A transfer is valued only for the record (SRS 3.4.7) and has no money in it: its legs count in the
        // quantities, which must reconcile with the balance, but not in the value totals.
        $totals = $this->base($filters)
            ->selectRaw('COALESCE(SUM(operations.qty_in), 0) AS in_qty, COALESCE(SUM(operations.qty_out), 0) AS out_qty,
                COALESCE(SUM(CASE WHEN operations.qty_in > 0 AND operations.type <> ? THEN operations.net_value ELSE 0 END), 0) AS in_value,
                COALESCE(SUM(CASE WHEN operations.qty_out > 0 AND operations.type <> ? THEN operations.net_value ELSE 0 END), 0) AS out_value', [MovementType::Transfer->value, MovementType::Transfer->value])
            ->first();

        $inQty = Money::quantity((string) $totals->in_qty);
        $outQty = Money::quantity((string) $totals->out_qty);

        return [
            'opening' => $opening,
            'closing' => Money::subtractQuantity(Money::sumQuantity([$opening, $inQty]), $outQty),
            'rows' => $rows,
            'in_qty' => $inQty,
            'out_qty' => $outQty,
            'in_value' => Money::money((string) $totals->in_value),
            'out_value' => Money::money((string) $totals->out_value),
        ];
    }

    // ---- stock -------------------------------------------------------------------------------------

    /**
     * Per product (or per product and warehouse when split): opening, in, out, remaining, minimum, last sale.
     * The period is [dateFrom, dateTo]; `dateTo` defaults to today and no `dateFrom` means "from the start".
     * Remaining is the balance at `dateTo`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function stock(ReportFilters $filters): Collection
    {
        $to = $filters->dateTo ?? today()->toDateString();
        $from = $filters->dateFrom ?? '0001-01-01';
        $split = $filters->splitByWarehouse && $filters->warehouseId === null;
        $sale = MovementType::Sale->value;

        $aggregates = Operation::query()
            ->where('operations.date', '<=', $to)
            ->when($filters->warehouseId, fn (Builder $q, int $id) => $q->where('operations.warehouse_id', $id))
            ->when($filters->productId, fn (Builder $q, int $id) => $q->where('operations.product_id', $id))
            ->groupBy($split ? ['operations.product_id', 'operations.warehouse_id'] : ['operations.product_id'])
            ->selectRaw(
                'operations.product_id AS product_id, '.($split ? 'operations.warehouse_id' : '0').' AS warehouse_id,
                COALESCE(SUM(CASE WHEN operations.date < ? THEN operations.qty_in - operations.qty_out ELSE 0 END), 0) AS opening,
                COALESCE(SUM(CASE WHEN operations.date >= ? THEN operations.qty_in ELSE 0 END), 0) AS qty_in,
                COALESCE(SUM(CASE WHEN operations.date >= ? THEN operations.qty_out ELSE 0 END), 0) AS qty_out,
                COALESCE(SUM(CASE WHEN operations.type = \''.$sale.'\' AND operations.date >= ? THEN operations.qty_out ELSE 0 END), 0) AS sold,
                MAX(CASE WHEN operations.type = \''.$sale.'\' THEN operations.date END) AS last_sale',
                [$from, $from, $from, $from],
            )
            ->get()
            ->keyBy(fn ($row) => $row->product_id.':'.$row->warehouse_id);

        $products = Product::query()
            ->when($filters->productId, fn (Builder $q, int $id) => $q->whereKey($id))
            ->where(fn (Builder $q) => $q->where('is_active', true)->orWhereIn('id', $aggregates->pluck('product_id')->unique()->all()))
            ->orderBy('name')
            ->get();

        $warehouses = $split ? $this->warehouses($aggregates->pluck('warehouse_id')->unique()->all()) : collect([null]);
        $zero = Money::quantity('0');
        $rows = collect();

        foreach ($products as $product) {
            foreach ($warehouses as $warehouse) {
                $row = $aggregates->get($product->id.':'.($warehouse?->id ?? 0));

                $opening = Money::quantity((string) ($row->opening ?? '0'));
                $in = Money::quantity((string) ($row->qty_in ?? '0'));
                $out = Money::quantity((string) ($row->qty_out ?? '0'));
                $sold = Money::quantity((string) ($row->sold ?? '0'));
                $remaining = Money::subtractQuantity(Money::sumQuantity([$opening, $in]), $out);
                $min = $product->min_stock === null ? null : Money::quantity((string) $product->min_stock);
                $lastSale = $row->last_sale ?? null;

                $entry = [
                    'product' => $product,
                    'warehouse' => $warehouse,
                    'opening' => $opening,
                    'qty_in' => $in,
                    'qty_out' => $out,
                    'remaining' => $remaining,
                    'min_stock' => $min,
                    'sold' => $sold,
                    'last_sale' => $lastSale,
                    'days_since_sale' => $lastSale === null ? null : (int) Carbon::parse($lastSale)->startOfDay()->diffInDays(Carbon::parse($to)->startOfDay(), true),
                    'low' => $min !== null && Money::compare($remaining, $min) <= 0,
                    'has_history' => $row !== null,
                    'stagnant' => Money::isPositive($remaining) && Money::compare($sold, $zero) === 0,
                ];

                if ($this->passesStockFilter($entry, $filters->stockFilter)) {
                    $rows->push($entry);
                }
            }
        }

        return $rows->values();
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function passesStockFilter(array $entry, ?StockFilter $filter): bool
    {
        return match ($filter) {
            // A product the scope never received is not "running low": it was never stocked there.
            StockFilter::BelowMin => $entry['low'] && $entry['has_history'],
            StockFilter::Moved => Money::isPositive($entry['sold']),
            StockFilter::Stagnant => $entry['stagnant'],
            null => true,
        };
    }

    // ---- daily and party ---------------------------------------------------------------------------

    /**
     * Net sales of one day per warehouse: Σ net_value of sales − Σ net_value of sale returns (SRS 3.6.3).
     * Every given warehouse gets a row, even with no sales.
     *
     * @param  Collection<int, User>  $warehouses
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array{invoices: int, sales: string, returns: string, net: string}}
     */
    public function dailySales(string $date, Collection $warehouses): array
    {
        $sale = MovementType::Sale->value;
        $return = MovementType::SaleReturn->value;

        $found = Operation::query()
            ->where('operations.date', $date)
            ->whereIn('operations.type', [$sale, $return])
            ->whereIn('operations.warehouse_id', $warehouses->pluck('id')->all())
            ->groupBy('operations.warehouse_id')
            ->selectRaw("operations.warehouse_id AS warehouse_id,
                COUNT(DISTINCT CASE WHEN operations.type = '{$sale}' THEN operations.movement_id END) AS invoices,
                COALESCE(SUM(CASE WHEN operations.type = '{$sale}' THEN operations.net_value ELSE 0 END), 0) AS sales,
                COALESCE(SUM(CASE WHEN operations.type = '{$return}' THEN operations.net_value ELSE 0 END), 0) AS returned")
            ->get()
            ->keyBy('warehouse_id');

        $rows = $warehouses->map(function (User $warehouse) use ($found) {
            $row = $found->get($warehouse->id);
            $sales = Money::money((string) ($row->sales ?? '0'));
            $returned = Money::money((string) ($row->returned ?? '0'));

            return [
                'warehouse' => $warehouse,
                'invoices' => (int) ($row->invoices ?? 0),
                'sales' => $sales,
                'returns' => $returned,
                'net' => Money::subtract($sales, $returned),
            ];
        })->values();

        return [
            'rows' => $rows,
            'totals' => [
                'invoices' => $rows->sum('invoices'),
                'sales' => Money::sum($rows->pluck('sales')),
                'returns' => Money::sum($rows->pluck('returns')),
                'net' => Money::sum($rows->pluck('net')),
            ],
        ];
    }

    /**
     * Movements of one day per type, counted once per movement (a transfer is one movement).
     *
     * @return array<string, int> type value => count
     */
    public function movementCounts(string $date, ?int $warehouseId = null): array
    {
        return Operation::query()
            ->where('operations.date', $date)
            ->when($warehouseId, fn (Builder $q, int $id) => $q->where('operations.warehouse_id', $id))
            ->groupBy('operations.type')
            ->selectRaw('operations.type AS type, COUNT(DISTINCT operations.movement_id) AS total')
            ->pluck('total', 'type')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * What a customer or supplier did in the period: invoices, returns, and the net dealing
     * (invoices − returns; there is no debt, everything is cash — SRS 3.6.5).
     *
     * @return array{invoices_count: int, invoices_total: string, returns_count: int, returns_total: string, net: string}
     */
    public function partySummary(ReportFilters $filters): array
    {
        $totals = $this->typeTotals($filters->with(types: []));

        $sum = function (array $types) use ($totals): array {
            $picked = array_intersect_key($totals, array_flip(array_map(fn (MovementType $t) => $t->value, $types)));

            return [array_sum(array_column($picked, 'movements')), Money::sum(array_column($picked, 'net_value'))];
        };

        [$invoicesCount, $invoicesTotal] = $sum([MovementType::Purchase, MovementType::Sale]);
        [$returnsCount, $returnsTotal] = $sum([MovementType::PurchaseReturn, MovementType::SaleReturn]);

        return [
            'invoices_count' => $invoicesCount,
            'invoices_total' => $invoicesTotal,
            'returns_count' => $returnsCount,
            'returns_total' => $returnsTotal,
            'net' => Money::subtract($invoicesTotal, $returnsTotal),
        ];
    }

    /**
     * A page of an in-memory list (the stock report builds its rows in PHP).
     *
     * @param  Collection<int, mixed>  $items
     */
    public function paginate(Collection $items, int $perPage, int $page): LengthAwarePaginator
    {
        return (new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]))
            ->withQueryString();
    }

    // ---- internals ---------------------------------------------------------------------------------

    /**
     * Operations narrowed by the filters. Column names are qualified because reports join other tables.
     *
     * @return Builder<Operation>
     */
    private function base(ReportFilters $filters): Builder
    {
        return Operation::query()
            ->when($filters->types !== [], fn (Builder $q) => $q->whereIn('operations.type', array_map(fn (MovementType $t) => $t->value, $filters->types)))
            ->when($filters->dateFrom, fn (Builder $q, string $date) => $q->where('operations.date', '>=', $date))
            ->when($filters->dateTo, fn (Builder $q, string $date) => $q->where('operations.date', '<=', $date))
            ->when($filters->warehouseId, fn (Builder $q, int $id) => $q->where('operations.warehouse_id', $id))
            ->when($filters->partyId, fn (Builder $q, int $id) => $q->where('operations.party_id', $id))
            ->when($filters->productId, fn (Builder $q, int $id) => $q->where('operations.product_id', $id));
    }

    /**
     * Joins the labels of an operation row: movement number, product, party, warehouses.
     *
     * @param  Builder<Operation>  $query
     * @return Builder<Operation>
     */
    private function labelled(Builder $query): Builder
    {
        return $query
            ->join('movements as m', 'm.id', '=', 'operations.movement_id')
            ->join('products as p', 'p.id', '=', 'operations.product_id')
            ->join('users as w', 'w.id', '=', 'operations.warehouse_id')
            ->join('users as sw', 'sw.id', '=', 'm.warehouse_id')
            ->leftJoin('users as tw', 'tw.id', '=', 'm.target_warehouse_id')
            ->leftJoin('parties as pa', 'pa.id', '=', 'operations.party_id')
            ->addSelect([
                'operations.id', 'operations.movement_id', 'operations.type', 'operations.date',
                'operations.qty_in', 'operations.qty_out', 'operations.unit_price', 'operations.net_value', 'operations.warehouse_id',
                'm.number AS movement_number', 'm.warehouse_id AS source_id',
                'p.name AS product_name', 'p.unit AS product_unit', 'p.barcode AS product_barcode',
                'pa.name AS party_name', 'w.name AS warehouse_name', 'sw.name AS source_name', 'tw.name AS target_name',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function detailRow(object $row): array
    {
        // Rows are Operation models, so `type` and `date` arrive already cast.
        $type = $row->type instanceof MovementType ? $row->type : MovementType::from($row->type);

        // A transfer's counterpart is the other warehouse: "← target" on the out row, "from source" on the in row.
        $counterpart = $type === MovementType::Transfer && (int) $row->warehouse_id !== (int) $row->source_id
            ? 'من '.$row->source_name
            : $type->counterpartLabel($row->party_name, $row->target_name);

        return [
            'movement_id' => (int) $row->movement_id,
            'number' => $type->displayNumber((int) $row->movement_number),
            'type' => $type,
            'date' => $row->date instanceof \DateTimeInterface ? $row->date->format('Y-m-d') : (string) $row->date,
            'product_name' => $row->product_name,
            'product_unit' => $row->product_unit,
            'warehouse_name' => $row->warehouse_name,
            'counterpart' => $counterpart,
            'qty_in' => Money::quantity((string) $row->qty_in),
            'qty_out' => Money::quantity((string) $row->qty_out),
            'unit_price' => Money::money((string) $row->unit_price),
            'net_value' => Money::money((string) $row->net_value),
        ];
    }

    /**
     * Balance of the filtered product / warehouse before a date (opening balance of a ledger).
     */
    private function balanceBefore(ReportFilters $filters, string $date): string
    {
        $row = $this->base($filters->with(dateFrom: null, dateTo: null, types: []))
            ->where('operations.date', '<', $date)
            ->selectRaw('COALESCE(SUM(operations.qty_in), 0) AS qty_in, COALESCE(SUM(operations.qty_out), 0) AS qty_out')
            ->first();

        return Money::subtractQuantity((string) $row->qty_in, (string) $row->qty_out);
    }

    /**
     * Warehouses of the signed-in user's company that are active or hold stock history.
     *
     * @param  list<int|string>  $withHistory
     * @return Collection<int, User>
     */
    private function warehouses(array $withHistory): Collection
    {
        return User::query()
            ->where('company_id', Auth::user()?->company_id)
            ->where('role', Role::Warehouse)
            ->where(fn (Builder $q) => $q->where('is_active', true)->orWhereIn('id', $withHistory))
            ->orderBy('name')
            ->get();
    }
}
