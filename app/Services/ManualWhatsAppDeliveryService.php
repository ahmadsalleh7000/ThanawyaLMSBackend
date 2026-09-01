<?php

namespace App\Services;

use App\Enums\NotificationDeliveryStatus;
use App\Models\GuardianInvitation;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManualWhatsAppDeliveryService
{
    public function recordOpened(
        User $recipientUser,
        string $templateKey,
        string $recipient,
        string $subject,
        string $body,
        array $payload = [],
        ?GuardianInvitation $invitation = null,
    ): NotificationDelivery {
        return DB::transaction(function () use ($recipientUser, $templateKey, $recipient, $subject, $body, $payload, $invitation): NotificationDelivery {
            $delivery = NotificationDelivery::query()
                ->where('channel', 'whatsapp_web')
                ->where('template_key', $templateKey)
                ->where('user_id', $recipientUser->id)
                ->when($invitation, fn ($query) => $query->where('guardian_invitation_id', $invitation->id))
                ->where('status', NotificationDeliveryStatus::Pending->value)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($delivery) {
                $delivery->update([
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'body' => $body,
                    'payload' => $payload,
                    'opened_at' => now(),
                    'attempts' => $delivery->attempts + 1,
                    'last_error' => null,
                ]);

                return $delivery->refresh();
            }

            return NotificationDelivery::create([
                'user_id' => $recipientUser->id,
                'guardian_invitation_id' => $invitation?->id,
                'template_key' => $templateKey,
                'channel' => 'whatsapp_web',
                'recipient' => $recipient,
                'locale' => 'ar',
                'subject' => $subject,
                'body' => $body,
                'payload' => $payload,
                'status' => NotificationDeliveryStatus::Pending,
                'attempts' => 1,
                'opened_at' => now(),
            ]);
        });
    }

    public function markSent(NotificationDelivery $delivery, User $admin): NotificationDelivery
    {
        return DB::transaction(function () use ($delivery, $admin): NotificationDelivery {
            $locked = NotificationDelivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->channel === 'whatsapp_web', 422);
            abort_if($locked->status === NotificationDeliveryStatus::Sent, 409, 'تم تأكيد إرسال هذه الرسالة من قبل.');
            abort_unless($locked->status === NotificationDeliveryStatus::Pending, 422);
            $locked->update([
                'status' => NotificationDeliveryStatus::Sent,
                'sent_at' => now(),
                'sent_by' => $admin->id,
                'failed_at' => null,
                'last_error' => null,
            ]);

            return $locked->refresh();
        });
    }
}
