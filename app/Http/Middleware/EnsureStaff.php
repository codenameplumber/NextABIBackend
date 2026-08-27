<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The console is for admins.
 *
 * Everyone else is walked to their own front door rather than shown an error —
 * a realtor who bookmarks /app has made a navigation mistake, not an attack.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return redirect()->route($user->homeRoute());
        }

        return $next($request);
    }
}
