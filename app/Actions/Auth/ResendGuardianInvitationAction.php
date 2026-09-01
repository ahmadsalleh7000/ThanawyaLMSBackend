<?php

namespace App\Actions\Auth;

use App\Enums\GuardianInvitationStatus;
use App\Events\GuardianInvitationCreated;
use App\Models\GuardianInvitation;
use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResendGuardianInvitationAction
{
    public function execute(GuardianInvitation $invitation): GuardianInvitation
    {
        $token = Str::random(64);
        $updated = DB::transaction(function () use ($invitation, $token): GuardianInvitation {
            $locked = GuardianInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [GuardianInvitationStatus::Pending, GuardianInvitationStatus::Expired], true)) {
                throw ValidationException::withMessages(['invitation' => ['This invitation cannot be resent.']]);
            }
            $cooldown = (int) config('guardian.resend_cooldown_minutes', 10);
            if ($locked->last_sent_at?->gt(now()->subMinutes($cooldown))) {
                throw ValidationException::withMessages(['invitation' => ["Wait {$cooldown} minutes before resending."]]);
            }
            $attempts = NotificationDelivery::where('guardian_invitation_id', $locked->id)->count();
            if ($attempts >= (int) config('guardian.max_delivery_attempts', 5)) {
                throw ValidationException::withMessages(['invitation' => ['The delivery limit has been reached. Contact support.']]);
            }
            $locked->update([
                'token_hash' => hash('sha256', $token),
                'status' => GuardianInvitationStatus::Pending,
                'expires_at' => now()->addHours((int) config('guardian.invitation_expires_hours', 48)),
                'accepted_at' => null,
                'rejected_at' => null,
            ]);

            return $locked;
        }, 3);

        event(new GuardianInvitationCreated($updated, $token));

        return $updated;
    }
}
