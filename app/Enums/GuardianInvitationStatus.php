<?php

namespace App\Enums;

enum GuardianInvitationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
