<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PlatformSettingsService
{
    public const DEFAULTS = [
        'academy_name_ar' => 'تِبيان', 'academy_name_en' => 'TIBYAN',
        'support_email' => 'support@example.com', 'default_locale' => 'ar',
        'registration_enabled' => true, 'maintenance_banner_enabled' => false,
        'maintenance_banner_ar' => '', 'maintenance_banner_en' => '',
        'instapay_destination' => '', 'vodafone_cash_number' => '',
        'orange_cash_number' => '', 'etisalat_cash_number' => '',
        'manual_payment_expires_minutes' => 1440,
        'reconciliation_variance_threshold' => 25,
        'reconciliation_overdue_days' => 1,
        'reconciliation_daily_summary_enabled' => true,
        'payment_receipt_retention_days' => 365,
        'database_backup_retention_days' => 30,
        'queue_stale_minutes' => 15,
        'observability_slow_request_ms' => 1000,
        'observability_failed_login_threshold' => 10,
        'observability_metrics_retention_days' => 30,
    ];

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    public function all(): array
    {
        return Cache::remember('platform-settings', 300, fn () => array_replace(self::DEFAULTS, PlatformSetting::pluck('value', 'key')->all()));
    }

    public function update(array $settings, User $actor): void
    {
        foreach ($settings as $key => $value) {
            PlatformSetting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => in_array($key, ['registration_enabled'], true) ? 'access' : 'general', 'updated_by' => $actor->id]);
        }
        Cache::forget('platform-settings');
    }
}
