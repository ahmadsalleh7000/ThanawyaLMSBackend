<?php

namespace Tests\Feature;

use App\Models\StudentOnboardingProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentGuidedTourTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_student_is_eligible_and_completion_persists_without_completing_placement(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student);
        $this->getJson('/api/student/onboarding')->assertOk()->assertJsonPath('data.tourStatus', null);
        $this->putJson('/api/student/onboarding/tour', ['status' => 'completed'])
            ->assertOk()->assertJsonPath('data.tourStatus', 'completed')->assertJsonPath('data.completed', false);
        $this->getJson('/api/student/onboarding')->assertOk()->assertJsonPath('data.tourStatus', 'completed');
        $this->assertNotNull($student->onboardingProfile()->first()->tour_dismissed_at);
    }

    public function test_skip_is_scoped_to_the_authenticated_student(): void
    {
        $first = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($first);
        $this->putJson('/api/student/onboarding/tour', ['status' => 'skipped', 'student_id' => $other->id])
            ->assertOk()->assertJsonPath('data.tourStatus', 'skipped');
        Sanctum::actingAs($other);
        $this->getJson('/api/student/onboarding')->assertOk()->assertJsonPath('data.tourStatus', null);
        $this->assertDatabaseHas('student_onboarding_profiles', ['student_id' => $first->id, 'tour_status' => 'skipped']);
    }

    public function test_skipped_tour_can_be_completed_and_a_replay_cannot_downgrade_completion(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_STUDENT]));
        $this->putJson('/api/student/onboarding/tour', ['status' => 'skipped'])->assertOk();
        $this->putJson('/api/student/onboarding/tour', ['status' => 'completed'])->assertOk();
        $this->putJson('/api/student/onboarding/tour', ['status' => 'skipped'])->assertOk()->assertJsonPath('data.tourStatus', 'completed');
    }

    public function test_only_supported_dismissal_states_are_accepted(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_STUDENT]));
        foreach ([[], ['status' => 'pending'], ['status' => null]] as $payload) {
            $this->putJson('/api/student/onboarding/tour', $payload)->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        $this->assertSame(0, StudentOnboardingProfile::count());
    }

    public function test_guests_and_other_roles_cannot_change_student_tour_state(): void
    {
        $this->putJson('/api/student/onboarding/tour', ['status' => 'completed'])->assertUnauthorized();
        foreach ([User::ROLE_PARENT, User::ROLE_TEACHER] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->putJson('/api/student/onboarding/tour', ['status' => 'completed'])->assertForbidden();
        }
    }
}
