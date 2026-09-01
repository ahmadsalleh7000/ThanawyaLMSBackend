<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\StudentOnboardingProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentPlanningApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_planner_combines_tasks_goal_streak_deadlines_and_badges(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');
        $student = User::factory()->create(['role' => 'student']);
        StudentOnboardingProfile::create(['student_id' => $student->id, 'weekly_goal_minutes' => 120]);
        $course = $this->course();
        $first = $course->lessons()->create(['slug' => 'intro', 'title' => 'المقدمة', 'title_en' => 'Introduction', 'duration_minutes' => 30, 'position' => 1]);
        $course->lessons()->create(['slug' => 'practice', 'title' => 'تطبيق', 'title_en' => 'Practice', 'duration_minutes' => 20, 'position' => 2]);
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now(), 'status' => 'active']);
        $student->lessonCompletions()->create(['lesson_id' => $first->id, 'completed_at' => now()]);
        Assessment::create(['course_id' => $course->id, 'duration_minutes' => 20, 'passing_score' => 70, 'is_published' => true, 'available_from' => now()->subDay(), 'due_at' => now()->addDays(3)]);
        Sanctum::actingAs($student);

        $this->getJson('/api/student/planner')->assertOk()
            ->assertJsonPath('data.todayTasks.0.id', 'practice')
            ->assertJsonPath('data.weeklyGoal.completedMinutes', 30)
            ->assertJsonPath('data.weeklyGoal.percent', 25)
            ->assertJsonPath('data.streak.current', 1)
            ->assertJsonPath('data.deadlines.0.courseId', $course->id)
            ->assertJsonPath('data.achievements.0.earned', true)
            ->assertJsonPath('data.leaderboard.enabled', false);

        Carbon::setTestNow();
    }

    public function test_leaderboard_is_opt_in_and_uses_a_privacy_safe_name(): void
    {
        $student = User::factory()->create(['name' => 'Ahmed Hassan', 'role' => 'student']);
        StudentOnboardingProfile::create(['student_id' => $student->id, 'leaderboard_opt_in' => false]);
        Sanctum::actingAs($student);

        $this->putJson('/api/student/planner/leaderboard', ['enabled' => true])
            ->assertOk()->assertJsonPath('data.enabled', true);
        $this->getJson('/api/student/planner')->assertOk()
            ->assertJsonPath('data.leaderboard.enabled', true)
            ->assertJsonPath('data.leaderboard.entries.0.name', 'Ahmed H.');
    }

    public function test_assessment_schedule_is_enforced(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course();
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now(), 'status' => 'active']);
        $assessment = Assessment::create(['course_id' => $course->id, 'duration_minutes' => 20, 'passing_score' => 70, 'is_published' => true, 'available_from' => now()->addDay()]);
        Sanctum::actingAs($student);

        $this->getJson("/api/courses/{$course->id}/assessment")->assertForbidden();
        $assessment->update(['available_from' => now()->subDays(2), 'due_at' => now()->subDay()]);
        $this->getJson("/api/courses/{$course->id}/assessment")->assertStatus(410);
    }

    private function course(): Course
    {
        return Course::create(['id' => 'planner-course', 'title' => 'برمجة', 'title_en' => 'Programming', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
    }
}
