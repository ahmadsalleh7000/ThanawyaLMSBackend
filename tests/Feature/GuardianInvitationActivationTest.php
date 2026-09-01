<?php

namespace Tests\Feature;

use App\Enums\GuardianInvitationStatus;
use App\Events\GuardianInvitationAccepted;
use App\Models\GuardianInvitation;
use App\Models\StudentOnboardingProfile;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GuardianInvitationActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_invitation_can_be_previewed_without_exposing_student_or_phone_data(): void
    {
        [$invitation, $token] = $this->invitation();

        $this->getJson("/api/auth/guardian-invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.relationshipType', 'father')
            ->assertJsonPath('data.activationMode', 'create_account')
            ->assertJsonMissingPath('data.student')
            ->assertJsonMissingPath('data.parentPhone');

        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_new_parent_activation_is_atomic_and_creates_a_verified_relationship(): void
    {
        Event::fake([Registered::class, GuardianInvitationAccepted::class]);
        [$invitation, $token, $student] = $this->invitation();

        $response = $this->postJson("/api/auth/guardian-invitations/{$token}/accept", $this->payload())
            ->assertCreated()
            ->assertJsonPath('user.role', User::ROLE_PARENT)
            ->assertJsonPath('user.phone_e164', '+201112345678')
            ->assertJsonPath('relationship.status', 'accepted')
            ->assertJsonStructure(['token']);

        $parent = User::where('email', 'parent@example.test')->firstOrFail();
        $this->assertNotNull($parent->phone_verified_at);
        $this->assertDatabaseHas('parent_student_links', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'relationship_type' => 'father',
            'grade' => 'grade-1',
            'status' => 'accepted',
        ]);
        $this->assertSame(GuardianInvitationStatus::Verified, $invitation->fresh()->status);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertSame($parent->id, $invitation->fresh()->parent_user_id);
        $this->assertNotEmpty($response->json('token'));
        Event::assertDispatched(Registered::class, fn (Registered $event) => $event->user->is($parent));
        Event::assertDispatched(GuardianInvitationAccepted::class);
    }

    public function test_invitation_is_single_use_and_cannot_override_role_or_phone(): void
    {
        [, $token] = $this->invitation();

        $this->postJson("/api/auth/guardian-invitations/{$token}/accept", $this->payload([
            'role' => 'admin',
            'phone' => '+201099999999',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['role', 'phone']);
        $this->assertDatabaseMissing('users', ['email' => 'parent@example.test']);

        $this->postJson("/api/auth/guardian-invitations/{$token}/accept", $this->payload())->assertCreated();
        $this->postJson("/api/auth/guardian-invitations/{$token}/accept", $this->payload([
            'email' => 'another@example.test',
        ]))->assertUnprocessable()->assertJsonValidationErrors('invitation');
        $this->assertDatabaseCount('parent_student_links', 1);
    }

    public function test_expired_and_invalid_invitations_cannot_activate_accounts(): void
    {
        [$invitation, $token] = $this->invitation(['expires_at' => now()->subMinute()]);

        $this->getJson("/api/auth/guardian-invitations/{$token}")
            ->assertOk()->assertJsonPath('data.status', 'expired');
        $this->postJson("/api/auth/guardian-invitations/{$token}/accept", $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('invitation');
        $this->assertSame(GuardianInvitationStatus::Expired, $invitation->fresh()->status);

        $invalid = str_repeat('x', 64);
        $this->getJson("/api/auth/guardian-invitations/{$invalid}")->assertNotFound();
        $this->postJson("/api/auth/guardian-invitations/{$invalid}/accept", $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('invitation');
        $this->assertDatabaseMissing('users', ['email' => 'parent@example.test']);
    }

    public function test_existing_parent_is_directed_to_sign_in_instead_of_recreating_the_account(): void
    {
        $parent = User::factory()->withEgyptianPhone('+201112345678')->create(['role' => User::ROLE_PARENT]);
        [, $token] = $this->invitation(['parent_user_id' => $parent->id]);

        $this->getJson("/api/auth/guardian-invitations/{$token}")
            ->assertOk()->assertJsonPath('data.activationMode', 'sign_in');
        $this->postJson("/api/auth/guardian-invitations/{$token}/accept", $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('invitation');
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('parent_student_links', 0);
    }

    private function invitation(array $overrides = []): array
    {
        $student = User::factory()->withEgyptianPhone('+201012345678')->create(['role' => User::ROLE_STUDENT]);
        StudentOnboardingProfile::create(['student_id' => $student->id, 'grade' => 'grade-1']);
        $token = str_repeat('a', 64);
        $invitation = GuardianInvitation::create(array_replace([
            'student_id' => $student->id,
            'parent_phone_e164' => '+201112345678',
            'relationship_type' => 'father',
            'token_hash' => hash('sha256', $token),
            'status' => GuardianInvitationStatus::Pending,
            'expires_at' => now()->addDay(),
        ], $overrides));

        return [$invitation, $token, $student];
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Ahmed Parent',
            'email' => 'parent@example.test',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ], $overrides);
    }
}
