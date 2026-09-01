<?php

namespace App\Providers;

use App\Contracts\GuardianInvitationTransport;
use App\Services\ConfiguredGuardianInvitationTransport;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GuardianInvitationTransport::class, ConfiguredGuardianInvitationTransport::class);
    }

    public function boot(): void
    {
        View::composer('admin.layout', function ($view): void {
            $admin = auth()->user();
            $view->with([
                'adminHeaderNotifications' => $admin?->userNotifications()->latest()->limit(6)->get() ?? collect(),
                'adminUnreadNotificationCount' => $admin?->userNotifications()->whereNull('read_at')->count() ?? 0,
            ]);
        });

        if (app()->isProduction() && config('deployment.require_https')) {
            URL::forceScheme('https');
        }
        ResetPassword::createUrlUsing(fn (object $user, string $token): string => config('app.frontend_url').'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset())
        );
        $heartbeat = function (): void {
            try {
                Cache::put('ops.queue_worker_heartbeat', now()->toISOString(), now()->addDay());
            } catch (Throwable) {
                // Queue processing must not fail because health telemetry is unavailable.
            }
        };
        Queue::before(fn (JobProcessing $event) => $heartbeat());
        Queue::after(fn (JobProcessed $event) => $heartbeat());
        Queue::exceptionOccurred(fn (JobExceptionOccurred $event) => $heartbeat());
    }
}
