<?php

namespace App\Console\Commands;

use App\Models\LoginEvent;
use App\Models\RequestMetric;
use App\Services\IncidentService;
use App\Services\PlatformSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class MonitorApplicationHealth extends Command
{
    protected $signature = 'ops:monitor-health';

    protected $description = 'Record scheduler heartbeat and detect security and performance anomalies';

    public function handle(PlatformSettingsService $settings, IncidentService $incidents): int
    {
        Cache::put('ops.scheduler_heartbeat', now()->toISOString(), now()->addDay());
        $failedLogins = LoginEvent::where('successful', false)->where('occurred_at', '>=', now()->subMinutes(15))->count();
        $threshold = (int) $settings->get('observability_failed_login_threshold');
        if ($failedLogins >= $threshold) {
            $incidents->record('security', 'critical', 'Failed login spike', "{$failedLogins} failed logins were recorded in 15 minutes.", ['count' => $failedLogins, 'window_minutes' => 15], 'security:failed-login-spike');
        }
        $slowThreshold = (int) $settings->get('observability_slow_request_ms');
        $slow = RequestMetric::where('occurred_at', '>=', now()->subMinutes(15))->where('duration_ms', '>=', $slowThreshold)->count();
        if ($slow >= 10) {
            $incidents->record('performance', 'warning', 'Slow request spike', "{$slow} slow requests were recorded in 15 minutes.", ['count' => $slow, 'threshold_ms' => $slowThreshold], 'performance:slow-request-spike');
        }
        RequestMetric::where('occurred_at', '<', now()->subDays((int) $settings->get('observability_metrics_retention_days')))->delete();
        $this->info("Heartbeat recorded; {$failedLogins} failed logins; {$slow} slow requests.");

        return self::SUCCESS;
    }
}
