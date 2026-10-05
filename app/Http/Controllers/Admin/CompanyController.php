<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExtendTrialRequest;
use App\Models\Company;
use App\Services\CompanySubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Platform owner: company list and subscription status (FR-02). Guarded by `role:super_admin`.
 */
class CompanyController extends Controller
{
    public function __construct(private CompanySubscriptionService $subscriptions) {}

    public function index(): View
    {
        $companies = Company::with('owner')->latest()->paginate(20);

        return view('admin.companies.index', compact('companies'));
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
