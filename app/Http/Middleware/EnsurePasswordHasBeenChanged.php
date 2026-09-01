<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordHasBeenChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->must_change_password) {
            return $next($request);
        }

        if ($request->is('api/auth/forced-password', 'api/auth/logout', 'api/auth/me')) {
            return $next($request);
        }

        return response()->json([
            'message' => 'You must create a new password before continuing.',
            'code' => 'password_change_required',
        ], 409);
    }
}
