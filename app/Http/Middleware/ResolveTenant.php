<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bind the signed-in user's agency for the rest of the request.
 *
 * Every tenant-owned model carries a global scope that reads this binding, so
 * it has to be in place before the first query runs. Doing it in middleware
 * rather than in each component means there is no code path where a developer
 * can forget — the scope either finds a tenant or the query is refused.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->tenant_id) {
            abort(403, 'This account is not attached to an agency.');
        }

        app()->instance('nexabi.tenant', $user->tenant);

        return $next($request);
    }
}
