<?php

namespace App\Enums;

enum GuardianRelationshipStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Revoked = 'revoked';
}
