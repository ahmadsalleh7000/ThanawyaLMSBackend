<?php

namespace App\Events;

use App\Models\GuardianInvitation;
use App\Models\ParentStudentLink;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class GuardianInvitationAccepted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public GuardianInvitation $invitation,
        public ParentStudentLink $relationship,
    ) {}
}
