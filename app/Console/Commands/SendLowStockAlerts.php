<?php

namespace App\Console\Commands;

use App\Enums\CompanyStatus;
use App\Enums\StockFilter;
use App\Mail\LowStockDigest;
use App\Models\Company;
use App\Services\ReportService;
use App\Support\ReportFilters;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

/**
 * The owner's daily low-stock e-mail. One mail per company, only when something is below its minimum,
 * never for a suspended company or an owner who switched it off in the company settings.
 * The report engine works for the signed-in user (company scope), so each company is processed as its owner.
 */
class SendLowStockAlerts extends Command
{
    protected $signature = 'wms:alerts:low-stock';

    protected $description = 'E-mail each owner the products that are below their minimum stock';

    public function handle(ReportService $reports): int
    {
        $sent = 0;

        Company::query()
            ->where('low_stock_alerts', true)
            ->where('status', '!=', CompanyStatus::Suspended)
            ->with('owner')
            ->each(function (Company $company) use ($reports, &$sent) {
                $owner = $company->owner;

                if ($owner === null || ! $owner->is_active) {
                    return;
                }

                Auth::setUser($owner);

                $rows = $reports->stock(new ReportFilters(dateTo: today()->toDateString(), splitByWarehouse: true, stockFilter: StockFilter::BelowMin));

                if ($rows->isNotEmpty()) {
                    Mail::to($owner->email)->send(new LowStockDigest($company, $rows));
                    $sent++;
                }
            });

        Auth::forgetUser();

        $this->info("Low-stock e-mails sent: {$sent}");

        return self::SUCCESS;
    }
}
