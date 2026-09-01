<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\StudentOnboardingProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_completes_placement_and_receives_a_matching_recommendation(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $course = $this->course('recommended-start', 'grade-1', 'Intermediate');
        $this->course('other-grade', 'grade-2', 'Intermediate');
        Sanctum::actingAs($student);

        $this->getJson('/api/student/onboarding')->assertOk()->assertJsonPath('data.completed', false)->assertJsonCount(4, 'questions');
        $this->putJson('/api/student/onboarding/preferences', [
            'grade' => 'grade-1', 'semester' => 'semester-1', 'learning_goal' => 'projects',
            'experience_level' => 'basic', 'weekly_goal_minutes' => 180,
        ])->assertOk()->assertJsonPath('data.weeklyGoalMinutes', 180);
        $this->postJson('/api/student/onboarding/placement', ['answers' => ['variables' => 'a', 'condition' => 'b', 'loop' => 'a', 'function' => 'c']])
            ->assertOk()->assertJsonPath('data.completed', true)->assertJsonPath('data.placementScore', 3)
            ->assertJsonPath('data.recommendedLevel', 'Intermediate')->assertJsonPath('data.recommendedCourse.id', $course->id);

        $this->assertDatabaseHas('student_onboarding_profiles', ['student_id' => $student->id, 'recommended_course_id' => $course->id, 'weekly_goal_minutes' => 180]);
    }

    public function test_preferences_can_be_revisited_and_parent_accounts_are_forbidden(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student);
        $payload = ['grade' => 'grade-1', 'semester' => 'semester-1', 'learning_goal' => 'exam_success', 'experience_level' => 'none', 'weekly_goal_minutes' => 60];
        $this->putJson('/api/student/onboarding/preferences', $payload)->assertOk();
        $this->putJson('/api/student/onboarding/preferences', array_merge($payload, ['semester' => 'semester-2', 'weekly_goal_minutes' => 300]))->assertOk()->assertJsonPath('data.weeklyGoalMinutes', 300);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PARENT]));
        $this->getJson('/api/student/onboarding')->assertForbidden();
    }

    public function test_student_can_skip_onboarding_and_the_choice_is_persisted(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student);

        $this->postJson('/api/student/onboarding/skip')
            ->assertOk()
            ->assertJsonPath('data.skipped', true)
            ->assertJsonPath('data.completed', false);

        $this->getJson('/api/student/onboarding')
            ->assertOk()
            ->assertJsonPath('data.skipped', true);
        $this->assertNotNull(StudentOnboardingProfile::where('student_id', $student->id)->value('skipped_at'));
    }

    public function test_placement_answers_use_text_storage_and_round_trip_as_an_encrypted_array(): void
    {
        $this->assertContains(Schema::getColumnType('student_onboarding_profiles', 'placement_answers'), ['text', 'longtext']);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $profile = StudentOnboardingProfile::create(['student_id' => $student->id, 'placement_answers' => ['variables' => 'a']]);

        $this->assertSame(['variables' => 'a'], $profile->fresh()->placement_answers);
        $raw = DB::table('student_onboarding_profiles')->where('id', $profile->id)->value('placement_answers');
        $this->assertStringNotContainsString('variables', $raw);
    }

    private function course(string $id, string $grade, string $level): Course
    {
        return Course::create(['id' => $id, 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => $grade, 'semester' => 'semester-1', 'level' => $level, 'duration_minutes' => 60, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
    }
}
