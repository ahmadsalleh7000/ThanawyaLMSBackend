<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->routeIs('media.stream')) {
            $response->headers->remove('X-Frame-Options');
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self' ".$this->frontendOrigin());
        }

        if (str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Content-Security-Policy', config('security.content_security_policy'));
        }

        if ($request->user() || $request->is('admin*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function frontendOrigin(): string
    {
        $frontendUrl = (string) config('cors.allowed_origins.0', env('FRONTEND_URL', 'http://localhost:3000'));

        return rtrim($frontendUrl, '/');
    }
}
