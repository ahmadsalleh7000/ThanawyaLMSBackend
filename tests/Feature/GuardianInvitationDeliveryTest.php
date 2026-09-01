<?php

namespace Tests\Feature;

use App\Contracts\GuardianInvitationTransport;
use App\Enums\GuardianInvitationStatus;
use App\Jobs\SendGuardianInvitation;
use App\Jobs\SendGuardianWelcomeCredentials;
use App\Models\GuardianInvitation;
use App\Models\ManualPaymentSubmission;
use App\Models\NotificationDelivery;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\GuardianInvitationDeliveryService;
use App\Services\ManualPaymentReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class GuardianInvitationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_prepares_guardian_account_without_queuing_communication(): void
    {
        Queue::fake();

        PlatformSetting::create(['key' => 'instapay_destination', 'value' => 'payments@instapay', 'group' => 'general']);
        cache()->forget('platform-settings');
        $this->post('/api/auth/register', [
            'name' => 'Ahmed Student',
            'email' => 'student@example.test',
            'student_phone' => '01012345678',
            'parent_phone' => '01112345678',
            'grade' => 'grade-1',
            'semester' => 'semester-1',
            'relationship_type' => 'father',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'subscription_plan_id' => SubscriptionPlan::firstOrFail()->id,
            'payment_channel' => 'instapay', 'transaction_id' => 'TX-INVITE-1001',
            'sender_identifier' => '01012345678', 'transferred_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $invitation = GuardianInvitation::firstOrFail();
        $this->assertDatabaseMissing('notification_deliveries', ['guardian_invitation_id' => $invitation->id]);
        app(ManualPaymentReviewService::class)->approve(ManualPaymentSubmission::firstOrFail(), User::factory()->create(['role' => User::ROLE_ADMIN]), 'Transfer verified.');
        $this->assertDatabaseMissing('notification_deliveries', ['guardian_invitation_id' => $invitation->id]);
        $this->assertNull($invitation->fresh()->last_sent_at);
        $this->assertTrue($invitation->fresh()->guardian_account_was_created);
        Queue::assertNotPushed(SendGuardianWelcomeCredentials::class);
    }

    public function test_delivery_job_builds_the_secure_url_at_send_time_and_records_provider_result(): void
    {
        Queue::fake();
        [$invitation, $token] = $this->invitation();
        $delivery = app(GuardianInvitationDeliveryService::class)->queue($invitation, $token);
        $transport = Mockery::mock(GuardianInvitationTransport::class);
        $transport->shouldReceive('send')->once()->withArgs(function (string $recipient, string $message, int $id) use ($invitation, $token): bool {
            return $recipient === '+201112345678'
                && $id === $invitation->id
                && str_contains($message, '/guardian-invitation/'.$token);
        })->andReturn('provider-message-1');

        (new SendGuardianInvitation($delivery->id, $token))->handle($transport);

        $this->assertDatabaseHas('notification_deliveries', [
            'id' => $delivery->id,
            'status' => 'sent',
            'attempts' => 1,
            'provider_message_id' => 'provider-message-1',
        ]);
    }

    public function test_student_can_resend_after_cooldown_and_old_token_is_invalidated(): void
    {
        Queue::fake();
        [$invitation, $oldToken, $student] = $this->invitation();
        app(GuardianInvitationDeliveryService::class)->queue($invitation, $oldToken);
        $oldHash = $invitation->fresh()->token_hash;
        $invitation->update(['last_sent_at' => now()->subMinutes(11)]);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/student/guardian-invitations/{$invitation->id}/resend")
            ->assertOk()->assertJsonPath('data.status', 'pending');

        $this->assertNotSame($oldHash, $invitation->fresh()->token_hash);
        $this->getJson('/api/auth/guardian-invitations/'.$oldToken)->assertNotFound();
        $this->assertDatabaseCount('notification_deliveries', 2);
        Queue::assertPushed(SendGuardianInvitation::class, 2);
    }

    public function test_resend_cooldown_delivery_limit_and_ownership_are_enforced(): void
    {
        Queue::fake();
        [$invitation, $token, $student] = $this->invitation();
        app(GuardianInvitationDeliveryService::class)->queue($invitation, $token);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/student/guardian-invitations/{$invitation->id}/resend")
            ->assertUnprocessable()->assertJsonValidationErrors('invitation');

        $outsider = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/student/guardian-invitations/{$invitation->id}/resend")
            ->assertNotFound();

        $invitation->update(['last_sent_at' => now()->subMinutes(11)]);
        foreach (range(2, 5) as $attempt) {
            NotificationDelivery::create([
                'guardian_invitation_id' => $invitation->id,
                'template_key' => 'guardian_invitation',
                'channel' => 'whatsapp',
                'recipient' => $invitation->parent_phone_e164,
                'locale' => 'ar',
                'subject' => 'Invitation',
                'body' => 'Redacted',
                'status' => 'failed',
            ]);
        }
        $this->actingAs($student, 'sanctum')
            ->postJson("/api/student/guardian-invitations/{$invitation->id}/resend")
            ->assertUnprocessable()->assertJsonValidationErrors('invitation');
    }

    public function test_student_can_securely_correct_guardian_phone_and_the_old_token_is_invalidated(): void
    {
        Queue::fake();
        [$invitation, $oldToken, $student] = $this->invitation();

        $this->actingAs($student, 'sanctum')->putJson("/api/student/guardian-invitations/{$invitation->id}", [
            'parent_phone' => '01212345678',
            'relationship_type' => 'mother',
        ])->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.parentPhoneMasked', '+20121••••678');

        $this->getJson('/api/auth/guardian-invitations/'.$oldToken)->assertNotFound();
        $this->assertDatabaseHas('guardian_invitations', [
            'id' => $invitation->id,
            'parent_phone_e164' => '+201212345678',
            'relationship_type' => 'mother',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'guardian_invitation.corrected']);
        $this->assertDatabaseCount('notification_deliveries', 1);
        Queue::assertPushed(SendGuardianInvitation::class, 1);
    }

    public function test_guardian_phone_correction_enforces_ownership_and_account_role(): void
    {
        Queue::fake();
        [$invitation, , $student] = $this->invitation();
        $outsider = User::factory()->withEgyptianPhone('+201099999999')->create(['role' => User::ROLE_STUDENT]);

        $this->actingAs($outsider, 'sanctum')->putJson("/api/student/guardian-invitations/{$invitation->id}", [
            'parent_phone' => '01212345678',
        ])->assertNotFound();

        $this->actingAs($student, 'sanctum')->putJson("/api/student/guardian-invitations/{$invitation->id}", [
            'parent_phone' => '01099999999',
        ])->assertUnprocessable()->assertJsonValidationErrors('parent_phone');
    }

    public function test_expiry_command_marks_only_overdue_pending_invitations(): void
    {
        [$expired] = $this->invitation(['expires_at' => now()->subMinute()]);
        [$active] = $this->invitation([], '+201022222222', '+201155555555');

        $this->artisan('guardians:expire-invitations')->assertSuccessful();

        $this->assertSame(GuardianInvitationStatus::Expired, $expired->fresh()->status);
        $this->assertSame(GuardianInvitationStatus::Pending, $active->fresh()->status);
    }

    private function invitation(
        array $overrides = [],
        string $studentPhone = '+201012345678',
        string $parentPhone = '+201112345678',
    ): array {
        $student = User::factory()->withEgyptianPhone($studentPhone)->create(['role' => User::ROLE_STUDENT]);
        $token = str_repeat((string) (($student->id % 9) + 1), 64);
        $invitation = GuardianInvitation::create(array_replace([
            'student_id' => $student->id,
            'parent_phone_e164' => $parentPhone,
            'relationship_type' => 'father',
            'token_hash' => hash('sha256', $token),
            'status' => GuardianInvitationStatus::Pending,
            'expires_at' => now()->addDay(),
        ], $overrides));

        return [$invitation, $token, $student];
    }
}
