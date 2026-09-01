<?php

namespace App\Actions\Auth;

use App\Enums\GuardianInvitationStatus;
use App\Events\GuardianInvitationCreated;
use App\Models\GuardianInvitation;
use App\Models\User;
use App\Support\PhoneNumbers\EgyptianPhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReplaceGuardianInvitationAction
{
    public function execute(GuardianInvitation $invitation, array $data): GuardianInvitation
    {
        $phone = EgyptianPhoneNumber::normalize($data['parent_phone']);
        $token = Str::random(64);
        $updated = DB::transaction(function () use ($invitation, $data, $phone, $token): GuardianInvitation {
            $locked = GuardianInvitation::with('student')->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [GuardianInvitationStatus::Pending, GuardianInvitationStatus::Expired], true)) {
                throw ValidationException::withMessages(['invitation' => ['This invitation can no longer be corrected.']]);
            }
            if ($phone === $locked->student->phone_e164) {
                throw ValidationException::withMessages(['parent_phone' => ['The guardian phone must be different from the student phone.']]);
            }
            $owner = User::where('phone_e164', $phone)->first();
            if ($owner && $owner->role !== User::ROLE_PARENT) {
                throw ValidationException::withMessages(['parent_phone' => ['This phone belongs to an account that cannot be linked as a guardian.']]);
            }
            if (GuardianInvitation::where('student_id', $locked->student_id)->where('parent_phone_e164', $phone)->whereKeyNot($locked->id)->exists()) {
                throw ValidationException::withMessages(['parent_phone' => ['An invitation already exists for this guardian.']]);
            }
            $locked->update([
                'parent_user_id' => $owner?->id,
                'parent_phone_e164' => $phone,
                'relationship_type' => $data['relationship_type'] ?? $locked->relationship_type,
                'relationship_details' => ($data['relationship_type'] ?? $locked->relationship_type->value) === 'other'
                    ? ($data['relationship_details'] ?? $locked->relationship_details)
                    : null,
                'token_hash' => hash('sha256', $token),
                'status' => GuardianInvitationStatus::Pending,
                'expires_at' => now()->addHours((int) config('guardian.invitation_expires_hours', 48)),
                'accepted_at' => null, 'rejected_at' => null, 'last_sent_at' => null,
            ]);

            return $locked;
        }, 3);
        event(new GuardianInvitationCreated($updated, $token));

        return $updated;
    }
}
