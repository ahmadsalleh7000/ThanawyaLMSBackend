<?php

namespace App\Jobs;

use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotificationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $deliveryId) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::findOrFail($this->deliveryId);
        if ($delivery->status === NotificationDeliveryStatus::Sent) {
            return;
        }
        $delivery->update(['status' => 'sending', 'attempts' => $delivery->attempts + 1, 'last_error' => null]);
        try {
            Mail::html($delivery->body, fn ($message) => $message->to($delivery->recipient)->subject($delivery->subject));
            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'failed_at' => null]);
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'retrying', 'last_error' => mb_substr($exception->getMessage(), 0, 4000)]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::whereKey($this->deliveryId)->update(['status' => 'failed', 'failed_at' => now(), 'last_error' => mb_substr($exception?->getMessage() ?? 'Queue job failed.', 0, 4000)]);
    }
}
