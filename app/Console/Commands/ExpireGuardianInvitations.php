<?php

namespace App\Console\Commands;

use App\Enums\GuardianInvitationStatus;
use App\Models\GuardianInvitation;
use Illuminate\Console\Command;

class ExpireGuardianInvitations extends Command
{
    protected $signature = 'guardians:expire-invitations';

    protected $description = 'Mark overdue guardian invitations as expired';

    public function handle(): int
    {
        $count = GuardianInvitation::where('status', GuardianInvitationStatus::Pending)
            ->where('expires_at', '<=', now())
            ->update(['status' => GuardianInvitationStatus::Expired]);
        $this->info("Expired {$count} guardian invitation(s).");

        return self::SUCCESS;
    }
}
