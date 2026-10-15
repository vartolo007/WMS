<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanySettingsRequest;
use App\Services\CompanySettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The owner's company settings page (FR-25). It only ever edits the logged-in owner's own company.
 */
class CompanySettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $company = $request->user()->company;

        Gate::authorize('updateSettings', $company);

        return view('settings.edit', compact('company'));
    }

    public function update(UpdateCompanySettingsRequest $request, CompanySettingsService $settings): RedirectResponse
    {
        $settings->update(
            $request->user()->company,
            $request->safe()->except('logo'),
            $request->file('logo'),
        );

        return redirect()->route('settings.edit')->with('status', 'تم حفظ الإعدادات.');
    }
}
