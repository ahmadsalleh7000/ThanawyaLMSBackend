<?php

namespace App\Services;

use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

class LoginEventService
{
    public function record(Request $request, ?User $user, string $email, string $guard, bool $successful, ?string $failureReason = null): void
    {
        try {
            LoginEvent::create(['user_id' => $user?->id, 'email' => mb_strtolower($email), 'guard' => $guard, 'successful' => $successful, 'failure_reason' => $failureReason, 'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000), 'occurred_at' => now()]);
        } catch (Throwable) {
            // Authentication must remain available while observability storage is unavailable.
        }
    }
}
