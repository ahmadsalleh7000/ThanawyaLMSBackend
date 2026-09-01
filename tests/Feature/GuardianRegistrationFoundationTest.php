<?php

namespace Tests\Feature;

use App\Enums\GuardianInvitationStatus;
use App\Enums\GuardianRelationshipType;
use App\Models\GuardianInvitation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardianRegistrationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_phone_numbers_are_unique_when_present(): void
    {
        User::factory()->withEgyptianPhone('+201012345678')->create();

        $this->expectException(QueryException::class);
        User::factory()->withEgyptianPhone('+201012345678')->create();
    }

    public function test_multiple_users_can_exist_before_a_phone_is_collected(): void
    {
        User::factory()->count(2)->create(['phone_e164' => null]);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_guardian_invitation_casts_lifecycle_fields_and_hides_the_token_hash(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $invitation = GuardianInvitation::create([
            'student_id' => $student->id,
            'parent_phone_e164' => '+201112345678',
            'relationship_type' => GuardianRelationshipType::Mother,
            'token_hash' => hash('sha256', 'secret-invitation-token'),
            'status' => GuardianInvitationStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);

        $this->assertSame(GuardianRelationshipType::Mother, $invitation->relationship_type);
        $this->assertSame(GuardianInvitationStatus::Pending, $invitation->status);
        $this->assertTrue($invitation->expires_at->isFuture());
        $this->assertArrayNotHasKey('token_hash', $invitation->toArray());
        $this->assertTrue($student->guardianInvitations()->whereKey($invitation->id)->exists());
    }

    public function test_duplicate_student_and_guardian_phone_invitations_are_prevented(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $attributes = [
            'student_id' => $student->id,
            'parent_phone_e164' => '+201212345678',
            'relationship_type' => GuardianRelationshipType::Guardian,
            'status' => GuardianInvitationStatus::Pending,
            'expires_at' => now()->addDay(),
        ];
        GuardianInvitation::create($attributes + ['token_hash' => hash('sha256', 'first-token')]);

        $this->expectException(QueryException::class);
        GuardianInvitation::create($attributes + ['token_hash' => hash('sha256', 'second-token')]);
    }
}
