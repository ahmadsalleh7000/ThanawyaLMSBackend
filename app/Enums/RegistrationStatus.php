<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case PendingPaymentReview = 'pending_payment_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function canTransitionTo(self $next): bool
    {
        return $this === self::PendingPaymentReview
            && in_array($next, [self::Approved, self::Rejected], true);
    }

    public function isEligibleForAccess(): bool
    {
        return $this === self::Approved;
    }
}
