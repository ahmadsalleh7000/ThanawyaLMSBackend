<?php

namespace App\Data;

use App\Models\GuardianInvitation;
use App\Models\ManualPaymentSubmission;
use App\Models\Order;
use App\Models\User;

final readonly class StudentRegistrationResult
{
    public function __construct(
        public User $student,
        public GuardianInvitation $invitation,
        public string $invitationToken,
        public ?ManualPaymentSubmission $paymentSubmission,
        public Order $order,
    ) {}
}
