<?php

namespace App\Actions\Auth;

use App\Data\GuardianActivationResult;
use App\Enums\GuardianInvitationStatus;
use App\Enums\GuardianRelationshipStatus;
use App\Events\GuardianInvitationAccepted;
use App\Models\GuardianInvitation;
use App\Models\ParentStudentLink;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateGuardianInvitationAction
{
    public function execute(string $plainTextToken, array $data): GuardianActivationResult
    {
        $preflight = GuardianInvitation::where('token_hash', hash('sha256', $plainTextToken))->first();
        if ($preflight?->status === GuardianInvitationStatus::Pending && $preflight->expires_at->isPast()) {
            $preflight->update(['status' => GuardianInvitationStatus::Expired]);
            throw ValidationException::withMessages(['invitation' => ['This invitation has expired.']]);
        }

        $result = DB::transaction(function () use ($plainTextToken, $data): GuardianActivationResult {
            $invitation = GuardianInvitation::query()
                ->where('token_hash', hash('sha256', $plainTextToken))
                ->lockForUpdate()
                ->first();

            if (! $invitation || $invitation->status !== GuardianInvitationStatus::Pending) {
                throw ValidationException::withMessages(['invitation' => ['This invitation is no longer available.']]);
            }
            if ($invitation->expires_at->isPast()) {
                throw ValidationException::withMessages(['invitation' => ['This invitation has expired.']]);
            }
            if ($invitation->parent_user_id || User::where('phone_e164', $invitation->parent_phone_e164)->exists()) {
                throw ValidationException::withMessages(['invitation' => ['Sign in with the invited parent account to continue.']]);
            }

            $parent = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone_e164' => $invitation->parent_phone_e164,
                'phone_verified_at' => now(),
                'password' => $data['password'],
                'role' => User::ROLE_PARENT,
            ]);
            $grade = $invitation->student()->with('onboardingProfile')->first()?->onboardingProfile?->grade;
            $relationship = ParentStudentLink::updateOrCreate(
                ['parent_id' => $parent->id, 'student_id' => $invitation->student_id],
                [
                    'relationship_type' => $invitation->relationship_type,
                    'relationship_details' => $invitation->relationship_details,
                    'grade' => $grade,
                    'status' => GuardianRelationshipStatus::Accepted,
                    'linked_at' => now(),
                    'invited_at' => $invitation->created_at,
                    'accepted_at' => now(),
                    'verified_at' => now(),
                    'rejected_at' => null,
                    'revoked_at' => null,
                ],
            );
            $invitation->update([
                'parent_user_id' => $parent->id,
                'status' => GuardianInvitationStatus::Verified,
                'accepted_at' => now(),
            ]);

            return new GuardianActivationResult($parent, $invitation, $relationship);
        }, 3);

        event(new Registered($result->parent));
        event(new GuardianInvitationAccepted($result->invitation, $result->relationship));

        return $result;
    }
}
