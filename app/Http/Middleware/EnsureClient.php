<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The portal is for buyers.
 *
 * Staff are sent to the console rather than shown an error — someone landing
 * on the wrong door should be walked to the right one.
 */
class EnsureClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->isStaff()) {
            return redirect()->route('app.dashboard');
        }

        if (! $user->customer_id) {
            abort(403, 'This account is not linked to a customer record.');
        }

        return $next($request);
    }
}
