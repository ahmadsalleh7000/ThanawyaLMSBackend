<?php

namespace App\Policies;

use App\Models\ParentStudentLink;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewLearningReport(User $user, User $student): Response
    {
        return $this->hasVerifiedRelationship($user, $student)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function purchaseFor(User $user, User $student): Response
    {
        if ($user->role === User::ROLE_STUDENT && $user->is($student) && $student->is_active) {
            return Response::allow();
        }

        return $this->hasVerifiedRelationship($user, $student)
            ? Response::allow()
            : Response::denyWithStatus(422, 'Select an approved linked student for this purchase.');
    }

    private function hasVerifiedRelationship(User $parent, User $student): bool
    {
        return $parent->role === User::ROLE_PARENT
            && $parent->is_active
            && $student->role === User::ROLE_STUDENT
            && $student->is_active
            && ParentStudentLink::query()
                ->where('parent_id', $parent->id)
                ->where('student_id', $student->id)
                ->where('status', 'accepted')
                ->whereNull('revoked_at')
                ->exists();
    }
}
