<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The one page open to users of a suspended company (FR-03). Company owners see how to reach the platform;
 * warehouse users are told to contact their owner.
 */
class SuspendedSubscriptionController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // After the company is reactivated nobody stays on this page.
        if (! $user->companyIsSuspended()) {
            return redirect()->route('dashboard');
        }

        $support = array_filter(config('wms.support'), filled(...));
        $whatsappNumber = isset($support['phone']) ? Str::of($support['phone'])->replaceMatches('/\D+/', '')->toString() : '';

        return view('subscription.suspended', [
            'isOwner' => $user->isOwner(),
            'support' => $support,
            'whatsappUrl' => $whatsappNumber !== '' ? "https://wa.me/{$whatsappNumber}" : null,
        ]);
    }
}
