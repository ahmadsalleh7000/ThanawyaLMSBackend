<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isEligibleForAccess(), 403, 'This account is not eligible to access the platform.');

        return $next($request);
    }
}
