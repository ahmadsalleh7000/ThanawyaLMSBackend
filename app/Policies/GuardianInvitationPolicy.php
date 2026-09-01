<?php

namespace App\Policies;

use App\Enums\GuardianInvitationStatus;
use App\Models\GuardianInvitation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GuardianInvitationPolicy
{
    public function resend(User $user, GuardianInvitation $invitation): Response
    {
        return $user->role === User::ROLE_STUDENT
            && $invitation->student_id === $user->id
            && in_array($invitation->status, [GuardianInvitationStatus::Pending, GuardianInvitationStatus::Expired], true)
                ? Response::allow()
                : Response::denyAsNotFound();
    }

    public function respond(User $user, GuardianInvitation $invitation): Response
    {
        $belongsToParent = $invitation->parent_user_id === $user->id
            || ($user->phone_e164 && hash_equals($invitation->parent_phone_e164, $user->phone_e164));

        return $user->role === User::ROLE_PARENT
            && $belongsToParent
            && $invitation->status === GuardianInvitationStatus::Pending
                ? Response::allow()
                : Response::denyAsNotFound();
    }

    public function replace(User $user, GuardianInvitation $invitation): Response
    {
        return $this->resend($user, $invitation);
    }
}
