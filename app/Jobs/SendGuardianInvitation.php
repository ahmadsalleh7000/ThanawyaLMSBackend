<?php

namespace App\Jobs;

use App\Contracts\GuardianInvitationTransport;
use App\Enums\GuardianInvitationStatus;
use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendGuardianInvitation implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $deliveryId,
        private readonly string $plainTextToken,
    ) {}

    public function handle(GuardianInvitationTransport $transport): void
    {
        $delivery = NotificationDelivery::with('guardianInvitation.student:id,name')->findOrFail($this->deliveryId);
        if ($delivery->status === NotificationDeliveryStatus::Sent) {
            return;
        }
        $invitation = $delivery->guardianInvitation;
        if (! $invitation || $invitation->status !== GuardianInvitationStatus::Pending || $invitation->expires_at->isPast()) {
            $delivery->update(['status' => 'cancelled', 'last_error' => 'Invitation is no longer active.']);

            return;
        }

        $delivery->update(['status' => 'sending', 'attempts' => $delivery->attempts + 1, 'last_error' => null]);
        $url = rtrim((string) config('app.frontend_url'), '/').'/guardian-invitation/'.$this->plainTextToken;
        $studentName = $invitation->student?->name ?? 'الطالب';
        $message = "دعاك {$studentName} لربط حساب ولي الأمر في TIBYAN. الرابط صالح لفترة محدودة: {$url}";

        try {
            $providerId = $transport->send($delivery->recipient, $message, $invitation->id);
            $delivery->update([
                'status' => 'sent',
                'provider_message_id' => $providerId,
                'sent_at' => now(),
                'failed_at' => null,
            ]);
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
