<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checked on every request of a signed-in user (FR-03, FR-05):
 * - a deactivated account loses its session and goes to the login page;
 * - a user of a suspended company is sent to the suspended-subscription page, the only page that stays open
 *   (besides logging out, which is outside this middleware). The platform owner is never blocked.
 */
class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isDeactivated()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => trans('auth.inactive')]);
        }

        if ($user?->companyIsSuspended() && ! $request->routeIs('subscription.suspended')) {
            return redirect()->route('subscription.suspended');
        }

        return $next($request);
    }
}
