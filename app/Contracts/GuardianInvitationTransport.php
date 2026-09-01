<?php

namespace App\Contracts;

interface GuardianInvitationTransport
{
    public function send(string $recipient, string $message, int $invitationId): ?string;
}
