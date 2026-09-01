<?php

namespace App\Models;

use App\Enums\NotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'guardian_invitation_id', 'template_id', 'template_key', 'channel', 'recipient', 'locale', 'subject', 'body', 'payload', 'status', 'attempts', 'provider_message_id', 'last_error', 'opened_at', 'sent_at', 'sent_by', 'failed_at'])]
class NotificationDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => NotificationDeliveryStatus::class,
            'opened_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class);
    }

    public function guardianInvitation()
    {
        return $this->belongsTo(GuardianInvitation::class);
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function isSent(): bool
    {
        return $this->status === NotificationDeliveryStatus::Sent;
    }
}
