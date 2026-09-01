<?php

namespace App\Services;

use App\Jobs\SendGuardianInvitation;
use App\Models\GuardianInvitation;
use App\Models\NotificationDelivery;

class GuardianInvitationDeliveryService
{
    public function queue(GuardianInvitation $invitation, string $plainTextToken): NotificationDelivery
    {
        $delivery = NotificationDelivery::create([
            'user_id' => $invitation->parent_user_id,
            'guardian_invitation_id' => $invitation->id,
            'template_key' => 'guardian_invitation',
            'channel' => 'whatsapp',
            'recipient' => $invitation->parent_phone_e164,
            'locale' => 'ar',
            'subject' => 'دعوة ربط ولي الأمر',
            'body' => 'سيتم إنشاء رابط التفعيل الآمن وقت الإرسال.',
            'payload' => ['invitation_id' => $invitation->id],
            'status' => 'queued',
        ]);
        $invitation->update(['last_sent_at' => now()]);
        SendGuardianInvitation::dispatch($delivery->id, $plainTextToken)->afterCommit();

        return $delivery;
    }
}
