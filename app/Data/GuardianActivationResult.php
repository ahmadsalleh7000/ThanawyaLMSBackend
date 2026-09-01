<?php

namespace App\Data;

use App\Models\GuardianInvitation;
use App\Models\ParentStudentLink;
use App\Models\User;

final readonly class GuardianActivationResult
{
    public function __construct(
        public User $parent,
        public GuardianInvitation $invitation,
        public ParentStudentLink $relationship,
    ) {}
}
