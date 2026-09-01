<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\ActivateGuardianInvitationAction;
use App\Actions\Auth\ReplaceGuardianInvitationAction;
use App\Actions\Auth\ResendGuardianInvitationAction;
use App\Enums\GuardianInvitationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptGuardianInvitationRequest;
use App\Http\Requests\Auth\ReplaceGuardianInvitationRequest;
use App\Models\GuardianInvitation;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GuardianInvitationController extends Controller
{
    public function studentIndex(Request $request): JsonResponse
    {
        $cooldown = (int) config('guardian.resend_cooldown_minutes', 10);
        $maxAttempts = (int) config('guardian.max_delivery_attempts', 5);
        $items = $request->user()->guardianInvitations()
            ->with(['deliveries' => fn ($query) => $query->latest()])
            ->latest()
            ->get()
            ->map(function (GuardianInvitation $invitation) use ($cooldown, $maxAttempts): array {
                $latest = $invitation->deliveries->first();
                $resendableStatus = in_array($invitation->status, [GuardianInvitationStatus::Pending, GuardianInvitationStatus::Expired], true);
                $retryAt = $invitation->last_sent_at?->addMinutes($cooldown);

                return [
                    'id' => $invitation->id,
                    'status' => $invitation->status->value,
                    'relationshipType' => $invitation->relationship_type->value,
                    'relationshipDetails' => $invitation->relationship_details,
                    'parentPhoneMasked' => preg_replace('/^(\+20\d{3})\d{4}(\d{3})$/', '$1••••$2', $invitation->parent_phone_e164),
                    'deliveryStatus' => $latest?->status,
                    'deliveryAttempts' => $invitation->deliveries->count(),
                    'lastSentAt' => $invitation->last_sent_at?->toISOString(),
                    'expiresAt' => $invitation->expires_at->toISOString(),
                    'canResend' => $resendableStatus
                        && $invitation->deliveries->count() < $maxAttempts
                        && (! $retryAt || $retryAt->isPast()),
                    'retryAt' => $retryAt?->isFuture() ? $retryAt->toISOString() : null,
                ];
            });

        return response()->json(['data' => $items]);
    }

    public function replace(ReplaceGuardianInvitationRequest $request, GuardianInvitation $invitation, ReplaceGuardianInvitationAction $replace, AuditService $audit): JsonResponse
    {
        Gate::forUser($request->user())->authorize('replace', $invitation);
        $before = $invitation->attributesToArray();
        $updated = $replace->execute($invitation, $request->validated());
        $audit->record($request, 'guardian_invitation.corrected', $updated, $before);

        return response()->json(['data' => [
            'id' => $updated->id, 'status' => $updated->status->value,
            'parentPhoneMasked' => preg_replace('/^(\+20\d{3})\d{4}(\d{3})$/', '$1••••$2', $updated->parent_phone_e164),
            'expiresAt' => $updated->expires_at->toISOString(),
        ]]);
    }

    public function resend(
        Request $request,
        GuardianInvitation $invitation,
        ResendGuardianInvitationAction $resend,
    ): JsonResponse {
        Gate::forUser($request->user())->authorize('resend', $invitation);
        $updated = $resend->execute($invitation);

        return response()->json(['data' => [
            'status' => $updated->status->value,
            'expiresAt' => $updated->expires_at->toISOString(),
            'lastSentAt' => $updated->last_sent_at?->toISOString(),
        ]]);
    }

    public function show(string $token): JsonResponse
    {
        $invitation = $this->findInvitation($token);
        if ($invitation->status === GuardianInvitationStatus::Pending && $invitation->expires_at->isPast()) {
            $invitation->update(['status' => GuardianInvitationStatus::Expired]);
        }

        return response()->json(['data' => [
            'status' => $invitation->status->value,
            'relationshipType' => $invitation->relationship_type->value,
            'expiresAt' => $invitation->expires_at->toISOString(),
            'activationMode' => ($invitation->parent_user_id
                || User::where('phone_e164', $invitation->parent_phone_e164)->exists())
                    ? 'sign_in'
                    : 'create_account',
        ]]);
    }

    public function accept(
        AcceptGuardianInvitationRequest $request,
        string $token,
        ActivateGuardianInvitationAction $activate,
    ): JsonResponse {
        $result = $activate->execute($token, $request->validated());
        $accessToken = $result->parent
            ->createToken($request->string('device_name', 'web')->toString())
            ->plainTextToken;

        return response()->json([
            'message' => 'Parent account activated and linked successfully.',
            'user' => $result->parent->fresh(),
            'token' => $accessToken,
            'relationship' => ['status' => 'accepted'],
        ], 201);
    }

    private function findInvitation(string $token): GuardianInvitation
    {
        abort_unless(strlen($token) === 64, 404);

        return GuardianInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
    }
}
