<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-group gate by role, e.g. `role:owner` or `role:owner,warehouse`.
 * Fine-grained checks stay in Policies.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $role) => Role::from($role), $roles);

        abort_unless(in_array($request->user()?->role, $allowed, true), 403);

        return $next($request);
    }
}
