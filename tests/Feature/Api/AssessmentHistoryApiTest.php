<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssessmentHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_receives_only_their_paginated_attempts_without_answer_data(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        [$course, $assessment] = $this->assessment('history-course', 70);
        $this->attempt($assessment, $student, 84, true, now()->subHour());
        $this->attempt($assessment, $student, 55, false, now());
        $this->attempt($assessment, $other, 100, true, now());
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/me/assessment-attempts')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.course.id', $course->id)
            ->assertJsonPath('data.0.score', 55)
            ->assertJsonPath('data.0.passingScore', 70)
            ->assertJsonPath('meta.summary.attempts', 2)
            ->assertJsonPath('meta.summary.passed', 1)
            ->assertJsonPath('meta.summary.bestScore', 84);

        $this->assertArrayNotHasKey('answers', $response->json('data.0'));
    }

    public function test_student_can_filter_results_and_course_without_cross_account_access(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        [, $first] = $this->assessment('first-history-course');
        [$secondCourse, $second] = $this->assessment('second-history-course');
        $this->attempt($first, $student, 90, true);
        $this->attempt($second, $student, 40, false);
        Sanctum::actingAs($student);

        $this->getJson('/api/me/assessment-attempts?result=needs_review&course_id='.$secondCourse->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.course.id', $secondCourse->id)
            ->assertJsonPath('meta.summary.averageScore', 40);

        $this->getJson('/api/me/assessment-attempts?result=unknown')->assertUnprocessable();
    }

    public function test_parent_cannot_open_student_assessment_history_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PARENT]));

        $this->getJson('/api/me/assessment-attempts')->assertForbidden();
    }

    public function test_parent_sees_attempt_summaries_only_in_a_linked_student_report(): void
    {
        $parent = User::factory()->create(['role' => User::ROLE_PARENT]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        [$course, $assessment] = $this->assessment('guardian-history-course');
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);
        $this->attempt($assessment, $student, 76, true);
        $parent->linkedStudents()->attach($student->id, [
            'grade' => 'grade-1',
            'linked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Sanctum::actingAs($parent);

        $this->getJson('/api/parent/students/'.$student->id)
            ->assertOk()
            ->assertJsonPath('data.assessmentHistory.0.courseId', $course->id)
            ->assertJsonPath('data.assessmentHistory.0.score', 76)
            ->assertJsonPath('data.assessmentHistory.0.passed', true);
        $this->getJson('/api/parent/students/'.$other->id)->assertNotFound();
    }

    private function assessment(string $courseId, int $passingScore = 60): array
    {
        $course = Course::factory()->create(['id' => $courseId, 'is_published' => true]);
        $assessment = Assessment::create([
            'course_id' => $course->id,
            'duration_minutes' => 20,
            'passing_score' => $passingScore,
            'is_published' => true,
        ]);

        return [$course, $assessment];
    }

    private function attempt(Assessment $assessment, User $student, int $score, bool $passed, $submittedAt = null): AssessmentAttempt
    {
        return AssessmentAttempt::create([
            'assessment_id' => $assessment->id,
            'user_id' => $student->id,
            'answers' => ['1' => 0],
            'score' => $score,
            'passed' => $passed,
            'submitted_at' => $submittedAt ?? now(),
        ]);
    }
}
