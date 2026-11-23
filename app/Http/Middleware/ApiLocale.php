<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            app()->setLocale(str_starts_with(strtolower((string) $request->header('Accept-Language', 'en')), 'ar') ? 'ar' : 'en');
        }

        return $next($request);
    }
}
