<?php

namespace App\Actions\Auth;

use App\Enums\GuardianInvitationStatus;
use App\Enums\GuardianRelationshipStatus;
use App\Events\GuardianInvitationAccepted;
use App\Models\GuardianInvitation;
use App\Models\ParentStudentLink;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideExistingGuardianInvitationAction
{
    public function execute(User $parent, GuardianInvitation $invitation, string $decision): GuardianInvitation
    {
        $relationship = null;
        $result = DB::transaction(function () use ($parent, $invitation, $decision, &$relationship): GuardianInvitation {
            $locked = GuardianInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            $belongsToParent = $locked->parent_user_id === $parent->id
                || hash_equals($locked->parent_phone_e164, (string) $parent->phone_e164);
            abort_unless($belongsToParent && $parent->role === User::ROLE_PARENT, 404);

            if ($locked->status !== GuardianInvitationStatus::Pending) {
                throw ValidationException::withMessages(['invitation' => ['This invitation is no longer available.']]);
            }
            if ($locked->expires_at->isPast()) {
                $locked->update(['status' => GuardianInvitationStatus::Expired]);

                return $locked;
            }

            if ($decision === 'reject') {
                $locked->update([
                    'parent_user_id' => $parent->id,
                    'status' => GuardianInvitationStatus::Rejected,
                    'rejected_at' => now(),
                ]);
                UserNotification::create([
                    'user_id' => $locked->student_id,
                    'type' => 'family_link',
                    'title' => 'Guardian invitation declined',
                    'title_ar' => 'تم رفض دعوة ولي الأمر',
                    'message' => 'The invited guardian declined the family link.',
                    'message_ar' => 'رفض ولي الأمر المدعو ربط الحسابين.',
                    'action_url' => '/dashboard',
                ]);

                return $locked;
            }

            $grade = $locked->student()->with('onboardingProfile')->first()?->onboardingProfile?->grade;
            $relationship = ParentStudentLink::updateOrCreate(
                ['parent_id' => $parent->id, 'student_id' => $locked->student_id],
                [
                    'relationship_type' => $locked->relationship_type,
                    'relationship_details' => $locked->relationship_details,
                    'grade' => $grade,
                    'status' => GuardianRelationshipStatus::Accepted,
                    'linked_at' => now(),
                    'invited_at' => $locked->created_at,
                    'accepted_at' => now(),
                    'verified_at' => now(),
                    'rejected_at' => null,
                    'revoked_at' => null,
                ],
            );
            $locked->update([
                'parent_user_id' => $parent->id,
                'status' => GuardianInvitationStatus::Verified,
                'accepted_at' => now(),
                'rejected_at' => null,
            ]);
            UserNotification::create([
                'user_id' => $locked->student_id,
                'type' => 'family_link',
                'title' => 'Guardian linked',
                'title_ar' => 'تم ربط ولي الأمر',
                'message' => 'Your guardian accepted the invitation and can now follow your progress.',
                'message_ar' => 'قبل ولي الأمر الدعوة ويمكنه الآن متابعة تقدمك.',
                'action_url' => '/dashboard',
            ]);

            return $locked;
        }, 3);

        if ($result->status === GuardianInvitationStatus::Expired) {
            throw ValidationException::withMessages(['invitation' => ['This invitation has expired.']]);
        }
        if ($relationship) {
            event(new GuardianInvitationAccepted($result, $relationship));
        }

        return $result;
    }
}
