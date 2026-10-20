<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Enums\StockFilter;
use App\Models\Movement;
use App\Models\User;
use App\Support\ReportFilters;
use Illuminate\Support\Collection;

/**
 * The numbers of the two company dashboards (FR-24, screens 5 and 6). Everything comes from the report
 * engine, so a dashboard card and the matching report can never disagree.
 */
class DashboardService
{
    /** Rows shown in the dashboard lists; the full list is one click away in the reports. */
    private const LIST_LIMIT = 8;

    public function __construct(private readonly ReportService $reports) {}

    /**
     * A warehouse user: today's net sales, today's movements, low stock of their own warehouse.
     *
     * @return array<string, mixed>
     */
    public function forWarehouse(User $warehouse): array
    {
        $today = today()->toDateString();
        $counts = $this->reports->movementCounts($today, $warehouse->id);
        $low = $this->reports->stock(new ReportFilters(dateTo: $today, warehouseId: $warehouse->id, stockFilter: StockFilter::BelowMin));

        return [
            'sales' => $this->reports->dailySales($today, collect([$warehouse]))['totals'],
            'movementCount' => array_sum($counts),
            'movementBreakdown' => $this->breakdown($counts),
            'movements' => $this->todayMovements($today),
            'lowCount' => $low->count(),
            'low' => $low->take(self::LIST_LIMIT),
        ];
    }

    /**
     * The owner: net sales per warehouse and in total (or only the warehouse chosen in the top bar), low-stock items,
     * the stagnant count and the subscription.
     *
     * @param  Collection<int, User>  $warehouses  the company's warehouses
     * @return array<string, mixed>
     */
    public function forOwner(User $owner, Collection $warehouses, ?User $selected): array
    {
        $today = today()->toDateString();
        $scope = $selected ? collect([$selected]) : $warehouses->filter(fn (User $w) => $w->is_active)->values();
        $stagnantDays = (int) config('wms.stagnant_days', 60);

        $sales = $this->reports->dailySales($today, $scope);
        $counts = $this->reports->movementCounts($today, $selected?->id);
        $low = $this->reports->stock(new ReportFilters(dateTo: $today, warehouseId: $selected?->id, splitByWarehouse: true, stockFilter: StockFilter::BelowMin));
        $stagnant = $this->reports->stock(new ReportFilters(
            dateFrom: today()->subDays($stagnantDays)->toDateString(),
            dateTo: $today,
            warehouseId: $selected?->id,
            stockFilter: StockFilter::Stagnant,
        ));

        return [
            'sales' => $sales,
            'movementCount' => array_sum($counts),
            'lowCount' => $low->count(),
            'low' => $low->take(self::LIST_LIMIT),
            'stagnantCount' => $stagnant->count(),
            'stagnantDays' => $stagnantDays,
            'subscription' => $this->subscription($owner),
        ];
    }

    /**
     * "4 بيع · 1 شراء": movement counts of the day in a fixed type order.
     *
     * @param  array<string, int>  $counts
     */
    private function breakdown(array $counts): string
    {
        return collect(MovementType::cases())
            ->filter(fn (MovementType $type) => ($counts[$type->value] ?? 0) > 0)
            ->map(fn (MovementType $type) => $counts[$type->value].' '.$type->tabLabel())
            ->implode(' · ');
    }

    /**
     * Today's confirmed movements of the signed-in warehouse (the movement scopes keep it to their own), newest first.
     *
     * @return Collection<int, Movement>
     */
    private function todayMovements(string $today): Collection
    {
        return Movement::query()
            ->with(['party', 'targetWarehouse'])
            ->where('status', MovementStatus::Confirmed)
            ->whereDate('date', $today)
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    /**
     * Status, trial end and how much of the trial has gone (for the progress bar).
     *
     * @return array{status: CompanyStatus, endsAt: ?\Carbon\CarbonInterface, daysLeft: int, expired: bool, usedPercent: int}
     */
    private function subscription(User $owner): array
    {
        $company = $owner->company;
        $trialDays = max(1, (int) config('wms.trial_days', 14));
        $isTrial = $company->status === CompanyStatus::Trial;

        return [
            'status' => $company->status,
            'endsAt' => $isTrial ? $company->trial_ends_at : null,
            'daysLeft' => $isTrial ? $company->trialDaysLeft() : 0,
            'expired' => $company->isTrialExpired(),
            'usedPercent' => $isTrial ? (int) round(max(0, min(100, 100 - $company->trialDaysLeft() / $trialDays * 100))) : 0,
        ];
    }
}
