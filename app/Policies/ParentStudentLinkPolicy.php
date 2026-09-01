<?php

namespace App\Policies;

use App\Models\ParentStudentLink;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ParentStudentLinkPolicy
{
    public function respond(User $user, ParentStudentLink $link): Response
    {
        return $user->role === User::ROLE_STUDENT
            && $link->student_id === $user->id
            && $link->status === 'pending'
                ? Response::allow()
                : Response::denyAsNotFound();
    }

    public function revoke(User $user, ParentStudentLink $link): Response
    {
        return in_array($user->id, [$link->parent_id, $link->student_id], true)
            && $link->status === 'accepted'
            && $link->revoked_at === null
                ? Response::allow()
                : Response::denyAsNotFound();
    }
}
