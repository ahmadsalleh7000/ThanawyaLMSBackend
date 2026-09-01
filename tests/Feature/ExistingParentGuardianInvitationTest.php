<?php

namespace Tests\Feature;

use App\Enums\GuardianInvitationStatus;
use App\Events\GuardianInvitationAccepted;
use App\Models\GuardianInvitation;
use App\Models\StudentOnboardingProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ExistingParentGuardianInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_lists_only_invitations_bound_to_their_account_or_phone(): void
    {
        $parent = User::factory()->withEgyptianPhone('+201112345678')->create(['role' => User::ROLE_PARENT]);
        [$mine] = $this->invitation(['parent_user_id' => $parent->id]);
        $this->invitation(['parent_phone_e164' => '+201155555555'], '+201022222222');

        $this->actingAs($parent, 'sanctum')->getJson('/api/parent/guardian-invitations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonMissingPath('data.0.student.email');
    }

    public function test_existing_parent_accepts_an_invitation_and_adds_a_sibling_relationship(): void
    {
        Event::fake([GuardianInvitationAccepted::class]);
        $parent = User::factory()->withEgyptianPhone('+201112345678')->create(['role' => User::ROLE_PARENT]);
        [$firstInvitation, $firstStudent] = $this->invitation(['parent_user_id' => $parent->id]);
        [$secondInvitation, $secondStudent] = $this->invitation(['parent_user_id' => $parent->id], '+201022222222');

        $this->actingAs($parent, 'sanctum')
            ->postJson("/api/parent/guardian-invitations/{$firstInvitation->id}/accept")
            ->assertOk()->assertJsonPath('data.status', 'verified');
        $this->postJson("/api/parent/guardian-invitations/{$secondInvitation->id}/accept")
            ->assertOk()->assertJsonPath('data.status', 'verified');

        $this->assertDatabaseHas('parent_student_links', [
            'parent_id' => $parent->id,
            'student_id' => $firstStudent->id,
            'status' => 'accepted',
            'grade' => 'grade-1',
        ]);
        $this->assertDatabaseHas('parent_student_links', [
            'parent_id' => $parent->id,
            'student_id' => $secondStudent->id,
            'status' => 'accepted',
        ]);
        $this->assertCount(2, $parent->fresh()->linkedStudents);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $firstStudent->id,
            'type' => 'family_link',
        ]);
        Event::assertDispatchedTimes(GuardianInvitationAccepted::class, 2);
    }

    public function test_parent_can_reject_their_invitation_without_creating_a_link(): void
    {
        $parent = User::factory()->withEgyptianPhone('+201112345678')->create(['role' => User::ROLE_PARENT]);
        [$invitation, $student] = $this->invitation(['parent_user_id' => $parent->id]);

        $this->actingAs($parent, 'sanctum')
            ->postJson("/api/parent/guardian-invitations/{$invitation->id}/reject")
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertSame(GuardianInvitationStatus::Rejected, $invitation->fresh()->status);
        $this->assertNotNull($invitation->fresh()->rejected_at);
        $this->assertDatabaseMissing('parent_student_links', ['student_id' => $student->id]);
    }

    public function test_parent_cannot_decide_another_guardians_invitation_or_replay_a_decision(): void
    {
        $parent = User::factory()->withEgyptianPhone('+201112345678')->create(['role' => User::ROLE_PARENT]);
        $outsider = User::factory()->withEgyptianPhone('+201155555555')->create(['role' => User::ROLE_PARENT]);
        [$invitation] = $this->invitation(['parent_user_id' => $parent->id]);

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/parent/guardian-invitations/{$invitation->id}/accept")
            ->assertNotFound();
        $this->actingAs($parent, 'sanctum')
            ->postJson("/api/parent/guardian-invitations/{$invitation->id}/accept")
            ->assertOk();
        $this->postJson("/api/parent/guardian-invitations/{$invitation->id}/accept")
            ->assertNotFound();
        $this->assertDatabaseCount('parent_student_links', 1);
    }

    public function test_expired_invitation_is_persisted_as_expired_and_cannot_link(): void
    {
        $parent = User::factory()->withEgyptianPhone('+201112345678')->create(['role' => User::ROLE_PARENT]);
        [$invitation] = $this->invitation([
            'parent_user_id' => $parent->id,
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($parent, 'sanctum')
            ->postJson("/api/parent/guardian-invitations/{$invitation->id}/accept")
            ->assertUnprocessable()->assertJsonValidationErrors('invitation');
        $this->assertSame(GuardianInvitationStatus::Expired, $invitation->fresh()->status);
        $this->assertDatabaseCount('parent_student_links', 0);
    }

    private function invitation(array $overrides = [], string $studentPhone = '+201012345678'): array
    {
        $student = User::factory()->withEgyptianPhone($studentPhone)->create(['role' => User::ROLE_STUDENT]);
        StudentOnboardingProfile::create(['student_id' => $student->id, 'grade' => 'grade-1']);
        $invitation = GuardianInvitation::create(array_replace([
            'student_id' => $student->id,
            'parent_phone_e164' => '+201112345678',
            'relationship_type' => 'father',
            'token_hash' => hash('sha256', fake()->unique()->uuid()),
            'status' => GuardianInvitationStatus::Pending,
            'expires_at' => now()->addDay(),
        ], $overrides));

        return [$invitation, $student];
    }
}
