<?php

namespace App\Events;

use App\Models\GuardianInvitation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GuardianInvitationCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public GuardianInvitation $invitation,
        public string $plainTextToken,
    ) {}
}
