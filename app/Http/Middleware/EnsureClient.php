<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The investor area.
 *
 * Anyone signed in who is not an investor is sent to their own area. An
 * investor with no linked record is a broken account rather than a wrong turn,
 * so that one is refused outright — there is nothing to show them.
 */
class EnsureClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->isInvestor()) {
            return redirect()->route($user->homeRoute());
        }

        if (! $user->customer_id) {
            abort(403, 'This account is not linked to an investor record.');
        }

        return $next($request);
    }
}
