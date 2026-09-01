<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\EmailDeliveryService;
use App\Services\ManualPaymentSettingsService;
use Illuminate\Console\Command;

class ExpireManualPaymentOrders extends Command
{
    protected $signature = 'payments:expire-manual-orders';

    protected $description = 'Expire abandoned manual-transfer orders and release coupon reservations';

    public function handle(ManualPaymentSettingsService $settings, EmailDeliveryService $emails): int
    {
        $expired = 0;
        Order::where('payment_provider', 'manual_transfer')->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes($settings->expiresMinutes()))
            ->with(['redemption', 'user'])->chunkById(100, function ($orders) use (&$expired, $emails) {
                foreach ($orders as $order) {
                    $order->update(['status' => 'expired', 'failed_at' => now()]);
                    $order->redemption?->update(['status' => 'released']);
                    $order->user->userNotifications()->create([
                        'type' => 'payment', 'title' => 'Payment order expired', 'title_ar' => 'انتهت مهلة طلب الدفع',
                        'message' => 'Create a new order if you still want to enroll.', 'message_ar' => 'أنشئ طلباً جديداً إذا كنت لا تزال ترغب في التسجيل.',
                        'action_url' => '/courses',
                    ]);
                    $emails->queue($order->user, 'payment_expired', ['order' => $order->public_id]);
                    $expired++;
                }
            });
        $this->info("Expired {$expired} manual payment orders.");

        return self::SUCCESS;
    }
}
