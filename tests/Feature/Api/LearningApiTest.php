<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LearningApiTest extends TestCase
{
    use RefreshDatabase;

    private function course(float $price = 0): Course
    {
        $course = Course::create(['id' => $price ? 'paid-1' : 'free-1', 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => $price, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
        $course->lessons()->create(['slug' => 'lesson-1', 'title' => 'درس', 'title_en' => 'Lesson', 'duration_minutes' => 10, 'position' => 1]);

        return $course;
    }

    public function test_paid_course_cannot_be_enrolled_without_confirmed_payment(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/courses/'.$this->course(350)->id.'/enroll')->assertUnprocessable();
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_enrollment_is_required_to_complete_a_lesson(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $course = $this->course();
        $this->postJson("/api/courses/{$course->id}/lessons/lesson-1/complete")->assertForbidden();
    }

    public function test_enrolled_student_can_complete_a_lesson_idempotently(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $course = $this->course();
        $this->postJson("/api/courses/{$course->id}/enroll")->assertCreated();
        $this->postJson("/api/courses/{$course->id}/lessons/lesson-1/complete")
            ->assertOk()->assertJsonPath('celebration', 'lessons_1');
        $this->postJson("/api/courses/{$course->id}/lessons/lesson-1/complete")
            ->assertOk()->assertJsonPath('celebration', null);
        $this->assertDatabaseCount('lesson_completions', 1);
        $this->getJson('/api/me/learning')->assertOk()->assertJsonPath('enrolledIds.0', $course->id)->assertJsonPath('completedLessons.free-1.0', 'lesson-1');
    }

    public function test_enrollment_is_required_to_save_lesson_workspace(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $course = $this->course();

        $this->patchJson("/api/courses/{$course->id}/lessons/lesson-1/workspace", ['note' => 'Private note'])
            ->assertForbidden();
        $this->assertDatabaseCount('lesson_workspace_states', 0);
    }

    public function test_enrolled_student_workspace_is_validated_and_persisted(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $course = $this->course();
        $this->postJson("/api/courses/{$course->id}/enroll")->assertCreated();

        $this->patchJson("/api/courses/{$course->id}/lessons/lesson-1/workspace", [
            'note' => 'Review loops before the quiz.',
            'is_bookmarked' => true,
            'playback_seconds' => 83,
            'playback_rate' => 1.25,
        ])->assertOk()
            ->assertJsonPath('data.note', 'Review loops before the quiz.')
            ->assertJsonPath('data.isBookmarked', true)
            ->assertJsonPath('data.playbackSeconds', 83)
            ->assertJsonPath('data.playbackRate', 1.25);

        $learning = $this->getJson('/api/me/learning')->assertOk();
        $this->assertSame('Review loops before the quiz.', $learning->json('lessonWorkspace')['free-1.lesson-1']['note']);
        $this->assertTrue($learning->json('lessonWorkspace')['free-1.lesson-1']['isBookmarked']);

        $this->patchJson("/api/courses/{$course->id}/lessons/lesson-1/workspace", ['playback_rate' => 3])
            ->assertUnprocessable();
        $this->assertDatabaseCount('lesson_workspace_states', 1);
    }

    public function test_learning_summary_reports_study_time_streak_and_achievement_progress(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');
        $user = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($user);
        $course = $this->course();
        $second = $course->lessons()->create(['slug' => 'lesson-2', 'title' => 'درس ٢', 'title_en' => 'Lesson 2', 'duration_minutes' => 15, 'position' => 2]);
        $first = $course->lessons()->where('slug', 'lesson-1')->first();
        $user->lessonCompletions()->create(['lesson_id' => $first->id, 'completed_at' => now()->subDay()]);
        $user->lessonCompletions()->create(['lesson_id' => $second->id, 'completed_at' => now()]);

        $this->getJson('/api/me/learning-summary')->assertOk()
            ->assertJsonPath('data.studyMinutes', 25)
            ->assertJsonPath('data.completedLessons', 2)
            ->assertJsonPath('data.currentStreak', 2)
            ->assertJsonPath('data.achievements.0.earned', true)
            ->assertJsonPath('data.achievements.1.progress', 2);
        Carbon::setTestNow();
    }
}
