<?php

namespace App\Policies;

use App\Models\LessonComment;
use App\Models\User;

class LessonCommentPolicy
{
    public function update(User $user, LessonComment $comment): bool
    {
        return $user->id === $comment->user_id;
    }

    public function delete(User $user, LessonComment $comment): bool
    {
        return $user->id === $comment->user_id
            || ($user->isAdmin() && $user->hasAdminPermission('content.manage'));
    }
}
