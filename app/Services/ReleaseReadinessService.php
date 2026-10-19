<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ReleaseReadinessService
{
    /** @return array<string, array{ok: bool, message: string}> */
    public function checks(): array
    {
        $environment = (string) app()->environment();
        $appUrl = (string) config('app.url');
        $frontendUrl = (string) config('deployment.frontend_url');
        $paymentProvider = (string) config('payments.provider');
        $manualChannels = collect(config('payments.manual_transfer.channels', []));
        $requireHttps = (bool) config('deployment.require_https', true);

        return [
            'environment' => $this->result(
                $environment === 'production',
                'APP_ENV must be production.',
            ),
            'debug' => $this->result(
                config('app.debug') === false,
                'APP_DEBUG must be false.',
            ),
            'application_key' => $this->result(
                filled(config('app.key')),
                'APP_KEY must be generated.',
            ),
            'application_url' => $this->result(
                filter_var($appUrl, FILTER_VALIDATE_URL) !== false
                    && (! $requireHttps || Str::startsWith($appUrl, 'https://')),
                'APP_URL must be a valid HTTPS URL.',
            ),
            'frontend_url' => $this->result(
                filter_var($frontendUrl, FILTER_VALIDATE_URL) !== false
                    && (! $requireHttps || Str::startsWith($frontendUrl, 'https://')),
                'FRONTEND_URL must be a valid HTTPS URL.',
            ),
            'database' => $this->result(
                in_array(config('database.default'), ['mysql', 'mariadb', 'pgsql'], true),
                'Use MySQL, MariaDB, or PostgreSQL in production.',
            ),
            'cache' => $this->result(
                ! in_array(config('cache.default'), ['array', 'null'], true),
                'Use a persistent cache store.',
            ),
            'queue' => $this->result(
                ! in_array(config('queue.default'), ['sync', 'null'], true),
                'Use an asynchronous queue connection.',
            ),
            'session' => $this->result(
                ! in_array(config('session.driver'), ['array', 'file'], true)
                    && config('session.secure') === true
                    && config('session.http_only') === true,
                'Use a shared session store with secure, HTTP-only cookies.',
            ),
            'trusted_proxies' => $this->result(
                count(config('security.trusted_proxies', [])) > 0,
                'Configure TRUSTED_PROXIES for the production load balancer or reverse proxy.',
            ),
            'api_token_expiration' => $this->result(
                is_int(config('sanctum.expiration'))
                    && config('sanctum.expiration') >= 5
                    && config('sanctum.expiration') <= 43_200,
                'Set SANCTUM_EXPIRATION between 5 and 43200 minutes.',
            ),
            'mail' => $this->result(
                ! in_array(config('mail.default'), ['log', 'array'], true)
                    && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false,
                'Configure a transactional mailer and valid sender address.',
            ),
            'payments' => $this->result(
                $paymentProvider === 'manual_transfer'
                    ? $manualChannels->contains(fn (array $channel) => filled($channel['destination'] ?? null))
                    : ($paymentProvider === 'stripe'
                        && filled(config('payments.stripe.secret'))
                        && filled(config('payments.stripe.webhook_secret'))),
                'Configure at least one manual payment destination or complete Stripe credentials.',
            ),
            'admin_credentials' => $this->result(
                $this->administratorReady(),
                'Provision an active super administrator or configure ADMIN_NAME, ADMIN_EMAIL and ADMIN_PASSWORD (at least 12 characters, at most 72 bytes).',
            ),
            'backup_disk' => $this->result(
                filled(config('deployment.backup_disk'))
                    && array_key_exists(config('deployment.backup_disk'), config('filesystems.disks', [])),
                'Configure a valid backup storage disk.',
            ),
        ];
    }

    public function ready(): bool
    {
        return collect($this->checks())->every('ok');
    }

    private function administratorReady(): bool
    {
        $admins = app(ProductionAdminService::class);
        if ($admins->hasConfiguredCredentials()) {
            return true;
        }

        try {
            return app()->environment('production') && Schema::hasTable('users') && $admins->hasActiveSuperAdmin();
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array{ok: bool, message: string} */
    private function result(bool $ok, string $failureMessage): array
    {
        return ['ok' => $ok, 'message' => $ok ? 'Configured.' : $failureMessage];
    }
}
