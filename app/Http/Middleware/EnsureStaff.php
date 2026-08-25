<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The console is for agency staff. Buyers get redirected to their portal. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->isStaff()) {
            return redirect()->route('portal.overview');
        }

        return $next($request);
    }
}
