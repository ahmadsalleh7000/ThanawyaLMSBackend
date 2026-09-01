<?php

namespace App\Policies;

use App\Models\ManualPaymentSubmission;
use App\Models\User;

class ManualPaymentSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAdminPermission('payments.review');
    }

    public function view(User $user, ManualPaymentSubmission $submission): bool
    {
        return $user->hasAdminPermission('payments.review');
    }

    public function review(User $user, ManualPaymentSubmission $submission): bool
    {
        return $user->hasAdminPermission('payments.review');
    }

    public function approve(User $user, ManualPaymentSubmission $submission): bool
    {
        return $user->hasAdminPermission('payments.approve');
    }

    public function overrideDuplicate(User $user, ManualPaymentSubmission $submission): bool
    {
        return $user->hasAdminPermission('payments.override_duplicates');
    }
}
