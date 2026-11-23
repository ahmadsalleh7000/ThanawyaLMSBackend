<?php

namespace App\Http\Middleware;

use App\Enums\RegistrationStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    private const PAYMENT_ONLY_PATHS = [
        'api/auth/me',
        'api/auth/logout',
        'api/me/subscription',
        'api/me/orders',
        'api/payments/manual/submit',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $paymentOnly = $user?->role === User::ROLE_STUDENT
            && in_array($user->registrationStatus(), [RegistrationStatus::PendingPaymentReview, RegistrationStatus::Rejected], true)
            && (in_array($request->path(), self::PAYMENT_ONLY_PATHS, true)
                || $request->is('api/support/tickets', 'api/support/tickets/*', 'api/support/messages/*/attachment'));

        abort_unless($user?->isEligibleForAccess() || $paymentOnly, 403, __('This account is not eligible to access the platform.'));

        return $next($request);
    }
}
