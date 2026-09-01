<?php

namespace App\Services;

use App\Contracts\GuardianInvitationTransport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ConfiguredGuardianInvitationTransport implements GuardianInvitationTransport
{
    public function send(string $recipient, string $message, int $invitationId): ?string
    {
        $driver = (string) config('guardian.delivery.driver', 'log');
        if ($driver === 'log') {
            Log::info('guardian_invitation.delivery_simulated', [
                'invitation_id' => $invitationId,
                'recipient_suffix' => substr($recipient, -4),
            ]);

            return 'simulated-'.$invitationId;
        }
        if ($driver !== 'http') {
            throw new RuntimeException('Guardian invitation delivery is not configured.');
        }

        $endpoint = (string) config('guardian.delivery.endpoint');
        $token = (string) config('guardian.delivery.token');
        if ($endpoint === '' || $token === '') {
            throw new RuntimeException('Guardian invitation provider credentials are missing.');
        }

        $response = Http::asJson()
            ->withToken($token)
            ->timeout((int) config('guardian.delivery.timeout_seconds', 10))
            ->post($endpoint, ['to' => $recipient, 'message' => $message])
            ->throw();

        return (string) ($response->json('id') ?: $response->json('message_id') ?: 'accepted-'.$invitationId);
    }
}
