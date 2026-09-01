<?php

namespace App\Services;

use App\Models\ManualPaymentSubmission;
use App\Models\PaymentReconciliationPeriod;
use App\Models\ReconciliationAlert;
use App\Models\User;

class ReconciliationMonitoringService
{
    public function __construct(private readonly PlatformSettingsService $settings, private readonly PaymentReconciliationService $reconciliation, private readonly EmailDeliveryService $emails) {}

    public function run(): array
    {
        $created = 0;
        $threshold = (float) $this->settings->get('reconciliation_variance_threshold');
        $overdueDays = (int) $this->settings->get('reconciliation_overdue_days');
        PaymentReconciliationPeriod::where('status', 'open')->with('lines')->each(function ($period) use ($threshold, $overdueDays, &$created) {
            $this->reconciliation->refresh($period);
            foreach ($period->lines as $line) {
                $active = $line->actual_inflow !== null && $line->actual_refunds !== null && abs($line->variance) >= $threshold;
                $created += $this->syncAlert($period, 'variance', $line->channel, $active, abs($line->variance), 'Statement variance for '.config('payments.manual_transfer.channels.'.$line->channel.'.label').' is '.number_format($line->variance, 2).' EGP.');
            }
            $isOverdue = $period->ends_on->endOfDay()->addDays($overdueDays)->isPast();
            $created += $this->syncAlert($period, 'overdue', null, $isOverdue, null, 'Reconciliation period is overdue and remains open.');
        });

        return ['created' => $created, 'open' => ReconciliationAlert::whereNull('resolved_at')->count()];
    }

    public function sendDailySummary(): int
    {
        if (! $this->settings->get('reconciliation_daily_summary_enabled')) {
            return 0;
        }
        $openAlerts = ReconciliationAlert::whereNull('resolved_at')->count();
        $overdue = PaymentReconciliationPeriod::where('status', 'open')->where('ends_on', '<', today())->count();
        $pendingReviews = ManualPaymentSubmission::whereIn('status', ['submitted', 'under_review'])->count();
        $sent = 0;
        $this->financeAdmins()->each(function ($admin) use ($openAlerts, $overdue, $pendingReviews, &$sent) {
            $alreadySent = $admin->userNotifications()->where('type', 'finance_daily_summary')->whereDate('created_at', today())->exists();
            if (! $alreadySent) {
                $admin->userNotifications()->create([
                    'type' => 'finance_daily_summary', 'title' => 'Daily finance summary', 'title_ar' => 'الملخص المالي اليومي',
                    'message' => "{$pendingReviews} payment reviews, {$openAlerts} reconciliation alerts, {$overdue} overdue periods.",
                    'message_ar' => "{$pendingReviews} تحويلات للمراجعة، {$openAlerts} تنبيهات مطابقة، {$overdue} فترات متأخرة.",
                    'action_url' => '/admin/reconciliation', 'data' => ['date' => today()->toDateString()],
                ]);
                $this->emails->queue($admin, 'finance_daily_summary', ['message' => "{$pendingReviews} payment reviews, {$openAlerts} alerts, {$overdue} overdue periods."]);
                $sent++;
            }
        });

        return $sent;
    }

    private function syncAlert(PaymentReconciliationPeriod $period, string $type, ?string $channel, bool $active, ?float $amount, string $message): int
    {
        $query = ReconciliationAlert::where('period_id', $period->id)->where('type', $type)->where('channel', $channel);
        $alert = $query->first();
        if (! $active) {
            $alert?->update(['resolved_at' => $alert->resolved_at ?: now()]);

            return 0;
        }
        $shouldNotify = ! $alert || $alert->resolved_at;
        $alert = ReconciliationAlert::updateOrCreate(['period_id' => $period->id, 'type' => $type, 'channel' => $channel], ['severity' => $amount !== null && $amount >= 500 ? 'critical' : 'warning', 'amount' => $amount, 'message' => $message, 'detected_at' => now(), 'resolved_at' => null, 'resolved_by' => null, 'resolution_note' => null]);
        if ($shouldNotify) {
            $this->financeAdmins()->each(function ($admin) use ($message, $period, $alert) {
                $admin->userNotifications()->create(['type' => 'reconciliation_alert', 'title' => 'Financial reconciliation alert', 'title_ar' => 'تنبيه مطابقة مالية', 'message' => $message, 'message_ar' => $message, 'action_url' => '/admin/reconciliation/'.$period->id, 'data' => ['alert_id' => $alert->id]]);
                $this->emails->queue($admin, 'finance_alert', ['message' => $message]);
            });
        }

        return $shouldNotify ? 1 : 0;
    }

    private function financeAdmins()
    {
        return User::where('role', User::ROLE_ADMIN)->where(fn ($query) => $query->whereNull('admin_role')->orWhere('admin_role', 'finance_manager'))->get();
    }
}
