<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReleaseReadinessTest extends TestCase
{
    public function test_release_check_rejects_unsafe_local_configuration(): void
    {
        $this->artisan('ops:release-check')->assertFailed();
    }

    public function test_release_check_accepts_complete_production_configuration(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config([
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.url' => 'https://api.ahmed-lms.example',
            'deployment.frontend_url' => 'https://ahmed-lms.example',
            'deployment.admin_email' => 'owner@ahmed-lms.example',
            'deployment.admin_password' => 'a-unique-production-secret',
            'deployment.backup_disk' => 's3',
            'database.default' => 'mysql',
            'cache.default' => 'database',
            'queue.default' => 'database',
            'session.driver' => 'database',
            'session.secure' => true,
            'session.http_only' => true,
            'security.trusted_proxies' => ['10.0.0.0/8'],
            'sanctum.expiration' => 10080,
            'mail.default' => 'smtp',
            'mail.from.address' => 'hello@ahmed-lms.example',
            'payments.provider' => 'manual_transfer',
            'payments.manual_transfer.channels.instapay.destination' => 'ahmed@instapay',
        ]);

        $this->artisan('ops:release-check')->assertSuccessful();
    }
}
