<?php

namespace App\Jobs;

use App\Contracts\GuardianInvitationTransport;
use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendGuardianWelcomeCredentials implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $deliveryId,
        private readonly ?string $temporaryPassword,
        private readonly bool $newAccount,
        private readonly string $studentName,
    ) {}

    public function handle(GuardianInvitationTransport $transport): void
    {
        $delivery = NotificationDelivery::findOrFail($this->deliveryId);
        if ($delivery->status === NotificationDeliveryStatus::Sent) {
            return;
        }

        $delivery->update(['status' => 'sending', 'attempts' => $delivery->attempts + 1, 'last_error' => null]);
        $loginUrl = rtrim((string) config('app.frontend_url'), '/').'/login';
        $localPhone = preg_replace('/^\+20/', '0', $delivery->recipient);
        $message = $this->newAccount
            ? "مرحباً بك في أكاديمية أحمد. تم اعتماد تسجيل {$this->studentName} وإنشاء حساب ولي الأمر.\nاسم الدخول: {$localPhone}\nكلمة المرور المؤقتة: {$this->temporaryPassword}\nتسجيل الدخول: {$loginUrl}\nسيُطلب منك إنشاء كلمة مرور جديدة فور الدخول. لا تشارك بياناتك مع أي شخص."
            : "مرحباً، تم ربط {$this->studentName} بحساب ولي الأمر في أكاديمية أحمد. يمكنك متابعة تقدمه من حسابك الحالي: {$loginUrl}";

        try {
            $providerId = $transport->send($delivery->recipient, $message, (int) $delivery->guardian_invitation_id);
            $delivery->update(['status' => 'sent', 'provider_message_id' => $providerId, 'sent_at' => now(), 'failed_at' => null]);
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'retrying', 'last_error' => mb_substr($exception->getMessage(), 0, 4000)]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::whereKey($this->deliveryId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'last_error' => mb_substr($exception?->getMessage() ?? 'Queue job failed.', 0, 4000),
        ]);
    }
}
