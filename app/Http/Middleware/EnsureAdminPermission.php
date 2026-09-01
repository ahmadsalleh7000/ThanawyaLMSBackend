<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, ?string $requiredPermission = null): Response
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403, 'Administrative access is required.');
        $routeName = (string) $request->route()?->getName();
        $permission = $requiredPermission ?: collect(config('admin_roles.routes'))->first(fn ($permission, $pattern) => Str::is($pattern, $routeName));
        abort_unless($permission && $user->hasAdminPermission($permission), 403, 'Your staff role does not allow this action.');

        return $next($request);
    }
}
