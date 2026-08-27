<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The realtor area, and the approval gate in front of it.
 *
 * Signing up makes you a realtor. An admin approving you makes you one who can
 * do anything — until then there is no code, no leads, no materials, and no
 * catalogue. A pending realtor sees one holding screen and nothing else.
 *
 * The gate lives here rather than in each component for the same reason the
 * tenant scope lives in a global scope: there must be no code path where
 * somebody can forget it.
 */
class EnsureRealtor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->isRealtor()) {
            return redirect()->route($user->homeRoute());
        }

        if (! $user->agent_id) {
            abort(403, 'This account is not linked to a realtor record.');
        }

        // Pending and suspended realtors are allowed exactly one destination.
        if (! $user->isApprovedRealtor() && ! $request->routeIs('realtor.pending')) {
            return redirect()->route('realtor.pending');
        }

        // An approved realtor has no reason to sit on the holding screen.
        if ($user->isApprovedRealtor() && $request->routeIs('realtor.pending')) {
            return redirect()->route('realtor.dashboard');
        }

        return $next($request);
    }
}
