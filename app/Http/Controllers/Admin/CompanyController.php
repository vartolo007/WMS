<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompanyFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExtendTrialRequest;
use App\Models\Company;
use App\Services\CompanySubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Platform owner: company list, subscription status and the platform dashboard (FR-02, FR-24).
 * Guarded by `role:super_admin`.
 */
class CompanyController extends Controller
{
    public function __construct(private CompanySubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        $filter = CompanyFilter::tryFrom((string) $request->query('status')) ?? CompanyFilter::All;

        $query = Company::query()->with('owner')->search($request->query('q'));
        $filter->apply($query);

        return view('admin.companies.index', [
            'companies' => $query->expiredFirst()->latest()->paginate(20)->withQueryString(),
            'filter' => $filter,
            'search' => (string) $request->query('q'),
            'counts' => $this->subscriptions->overview(),
        ]);
    }

    public function activate(Company $company): RedirectResponse
    {
        $this->subscriptions->activate($company);

        return back()->with('status', "تم تفعيل {$company->name}.");
    }

    public function suspend(Company $company): RedirectResponse
    {
        $this->subscriptions->suspend($company);

        return back()->with('status', "تم إيقاف {$company->name}.");
    }

    public function extendTrial(ExtendTrialRequest $request, Company $company): RedirectResponse
    {
        $this->subscriptions->extendTrial($company, $request->integer('days'));

        return back()->with('status', "تم تمديد تجربة {$company->name}.");
    }
}
