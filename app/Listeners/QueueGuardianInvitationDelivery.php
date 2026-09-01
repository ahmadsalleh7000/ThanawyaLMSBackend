<?php

namespace App\Listeners;

use App\Events\GuardianInvitationCreated;
use App\Services\GuardianInvitationDeliveryService;

class QueueGuardianInvitationDelivery
{
    public function __construct(private readonly GuardianInvitationDeliveryService $deliveries) {}

    public function handle(GuardianInvitationCreated $event): void
    {
        $this->deliveries->queue($event->invitation, $event->plainTextToken);
    }
}
