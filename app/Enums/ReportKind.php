<?php

namespace App\Enums;

use App\Models\User;
use App\Support\ReportFilters;

/**
 * The 14 derived reports of SRS 3.6.2. Each one is the engine plus a preset of filters;
 * everything that varies by report lives here.
 */
enum ReportKind: string
{
    case DailySales = 'daily-sales';
    case DailyMovements = 'daily-movements';
    case SalesTotal = 'sales-total';
    case SalesByProduct = 'sales-by-product';
    case PurchasesTotal = 'purchases-total';
    case PurchasesBySupplier = 'purchases-by-supplier';
    case Returns = 'returns';
    case Transfers = 'transfers';
    case ProductLedger = 'product-ledger';
    case PartyMovement = 'party-movement';
    case Stock = 'stock';
    case LowStock = 'low-stock';
    case Moved = 'moved';
    case Stagnant = 'stagnant';

    public function label(): string
    {
        return match ($this) {
            self::DailySales => 'مبيعات اليوم لكل مستودع',
            self::DailyMovements => 'الحركة اليومية',
            self::SalesTotal => 'إجمالي المبيعات',
            self::SalesByProduct => 'مبيعات مادة معينة',
            self::PurchasesTotal => 'إجمالي المشتريات',
            self::PurchasesBySupplier => 'مشتريات مورد معين',
            self::Returns => 'المرتجعات',
            self::Transfers => 'حركات النقل',
            self::ProductLedger => 'حركة مادة',
            self::PartyMovement => 'حركة عميل أو مورد',
            self::Stock => 'جرد المواد',
            self::LowStock => 'المواد المنخفضة',
            self::Moved => 'المواد المتحركة',
            self::Stagnant => 'المواد الراكدة',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DailySales => 'مبيعات، مرتجعات، صافي',
            self::DailyMovements => 'كل ما اشتُري وبيع وأُرجع ونُقل',
            self::SalesTotal => 'مجمّعة حسب مادة أو عميل أو مستودع',
            self::SalesByProduct => 'سطور مبيعات المادة',
            self::PurchasesTotal => 'مجمّعة حسب مادة أو مورد أو مستودع',
            self::PurchasesBySupplier => 'فواتير المورد وموادها',
            self::Returns => 'مرتجعات الشراء والبيع',
            self::Transfers => 'إخراج من المصدر وإدخال بالهدف',
            self::ProductLedger => 'رصيد أول المدة والحركات',
            self::PartyMovement => 'فواتيره ومرتجعاته وصافي التعامل',
            self::Stock => 'الداخل والخارج والمتبقي',
            self::LowStock => 'الرصيد أقل من الحد الأدنى أو يساويه',
            self::Moved => 'اللي انباع منها بالفترة',
            self::Stagnant => 'ما انباع منها بالفترة وإلها رصيد',
        };
    }

    /**
     * Which partial shows the result.
     */
    public function view(): string
    {
        return match ($this) {
            self::DailySales => 'daily-sales',
            self::SalesTotal, self::PurchasesTotal => 'summary',
            self::ProductLedger => 'ledger',
            self::PartyMovement => 'party',
            self::Stock, self::LowStock, self::Moved, self::Stagnant => 'stock',
            default => 'details',
        };
    }

    /**
     * Movement types the report is limited to (empty = all).
     *
     * @return list<MovementType>
     */
    public function types(): array
    {
        return match ($this) {
            self::SalesTotal, self::SalesByProduct => [MovementType::Sale],
            self::PurchasesTotal, self::PurchasesBySupplier => [MovementType::Purchase],
            self::Returns => [MovementType::PurchaseReturn, MovementType::SaleReturn],
            self::Transfers => [MovementType::Transfer],
            default => [],
        };
    }

    /**
     * day: one date. range: from – to. asof: up to a date (the stock reports), with an optional start.
     */
    public function period(): string
    {
        return match ($this) {
            self::DailySales, self::DailyMovements => 'day',
            self::Stock, self::LowStock => 'asof',
            default => 'range',
        };
    }

    public function needsProduct(): bool
    {
        return match ($this) {
            self::SalesByProduct, self::ProductLedger => true,
            default => false,
        };
    }

    /**
     * The party must be chosen, and which kind the list offers (null = customers and suppliers).
     */
    public function needsParty(): bool
    {
        return $this === self::PurchasesBySupplier || $this === self::PartyMovement;
    }

    public function partyType(): ?PartyType
    {
        return $this === self::PurchasesBySupplier ? PartyType::Supplier : null;
    }

    public function stockFilter(): ?StockFilter
    {
        return match ($this) {
            self::LowStock => StockFilter::BelowMin,
            self::Moved => StockFilter::Moved,
            self::Stagnant => StockFilter::Stagnant,
            default => null,
        };
    }

    public function hasGrouping(): bool
    {
        return $this === self::SalesTotal || $this === self::PurchasesTotal;
    }

    public function isStock(): bool
    {
        return $this->view() === 'stock';
    }

    /**
     * The filters of this report from a validated request, with this report's defaults for the period:
     * day reports → today; range reports → the last 30 days (stagnant: the dashboard period); as-of → today.
     * A warehouse user is always limited to their own warehouse.
     *
     * @param  array<string, mixed>  $input  date, from, to, warehouse_id, party_id, product_id, group, split
     */
    public function filters(array $input, User $user): ReportFilters
    {
        $today = today();

        [$from, $to] = match ($this->period()) {
            'day' => [$input['date'] ?? $today->toDateString(), $input['date'] ?? $today->toDateString()],
            'asof' => [$input['from'] ?? null, $input['to'] ?? $today->toDateString()],
            default => [
                $input['from'] ?? $today->copy()->subDays($this === self::Stagnant ? (int) config('wms.stagnant_days', 60) : 29)->toDateString(),
                $input['to'] ?? $today->toDateString(),
            ],
        };

        return (new ReportFilters(
            types: $this->types(),
            dateFrom: $from,
            dateTo: $to,
            warehouseId: isset($input['warehouse_id']) ? (int) $input['warehouse_id'] : null,
            partyId: isset($input['party_id']) ? (int) $input['party_id'] : null,
            productId: isset($input['product_id']) ? (int) $input['product_id'] : null,
            stockFilter: $this->stockFilter(),
            groupBy: $input['group'] ?? 'product',
            splitByWarehouse: (bool) ($input['split'] ?? false),
        ))->forUser($user);
    }
}
