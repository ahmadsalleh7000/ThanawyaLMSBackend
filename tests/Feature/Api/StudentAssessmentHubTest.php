<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentAssessmentHubTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'scope' => 'academic', 'title' => 'دورة البرمجة', 'title_en' => 'Programming']);
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);
        $lesson = $course->lessons()->create(['slug' => 'intro', 'title' => 'مقدمة', 'title_en' => 'Introduction', 'position' => 1, 'status' => 'published']);
        $exam = Assessment::create(['course_id' => $course->id, 'is_published' => true, 'max_retries' => 1]);
        $exam->questions()->create(['prompt' => 'Question', 'prompt_ar' => 'سؤال', 'topic' => 'Basics', 'topic_ar' => 'أساسيات', 'options' => ['A', 'B'], 'options_ar' => ['أ', 'ب'], 'correct_option' => 0, 'explanation' => 'Secret answer', 'explanation_ar' => 'الإجابة', 'position' => 1]);
        $quiz = $lesson->trainings()->create(['title_ar' => 'تدريب المقدمة', 'title_en' => 'Intro quiz', 'type' => 'quiz', 'status' => 'published', 'position' => 1, 'max_retries' => 0]);
        $quiz->questions()->create(['prompt' => 'Quiz question', 'prompt_ar' => 'سؤال التدريب', 'topic' => 'Basics', 'topic_ar' => 'أساسيات', 'options' => ['A', 'B'], 'options_ar' => ['أ', 'ب'], 'correct_option' => 0, 'explanation' => 'Secret quiz answer', 'explanation_ar' => 'الإجابة']);
        Sanctum::actingAs($student);

        return [$student, $course, $lesson, $exam, $quiz];
    }

    public function test_student_gets_accessible_published_exams_and_quizzes_without_answers_or_other_students_scores(): void
    {
        [$student, $course, $lesson, $exam, $quiz] = $this->fixture();
        $other = User::factory()->create();
        $exam->attempts()->create(['user_id' => $other->id, 'score' => 100, 'passed' => true, 'answers' => [], 'submitted_at' => now()]);
        $hidden = Course::factory()->create(['is_published' => true, 'scope' => 'academic']);
        Assessment::create(['course_id' => $hidden->id, 'is_published' => true]);
        $response = $this->getJson('/api/me/assessments')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.summary.attempts', 0)->assertJsonPath('meta.summary.averageBestScore', null);
        $response->assertDontSee('Secret answer')->assertDontSee('correct_option')->assertDontSee('Quiz question');
        $items = collect($response->json('data'))->keyBy('type');
        $this->assertNull($items['exam']['bestScore']);
        $this->assertSame('not_started', $items['exam']['result']);
        $this->assertTrue($items['quiz']['canStart']);
        $this->assertStringContainsString('solvePractice='.$quiz->id, $items['quiz']['url']);
        $this->assertSame(2, $items['exam']['policy']['attemptsRemaining']);
        $this->getJson('/api/me/assessments/quiz/'.$quiz->id.'/attempts')->assertNotFound();
    }

    public function test_best_latest_scores_retries_and_combined_summary_are_personal(): void
    {
        [$student, , , $exam, $quiz] = $this->fixture();
        foreach ([[90, true, now()->subDay()], [40, false, now()]] as [$score, $passed, $date]) {
            $exam->attempts()->create(['user_id' => $student->id, 'score' => $score, 'passed' => $passed, 'answers' => [], 'submitted_at' => $date]);
        }
        $quiz->attempts()->create(['student_id' => $student->id, 'score' => 80, 'status' => 'passed', 'answers' => [], 'started_at' => now()->subMinute(), 'submitted_at' => now()]);
        $response = $this->getJson('/api/me/assessments')->assertOk()->assertJsonPath('meta.summary.attempts', 3)->assertJsonPath('meta.summary.passed', 2)->assertJsonPath('meta.summary.averageBestScore', 85);
        $items = collect($response->json('data'))->keyBy('type');
        $this->assertEquals(90, $items['exam']['bestScore']);
        $this->assertEquals(40, $items['exam']['latestScore']);
        $this->assertSame('attempt_limit', $items['exam']['blockedReason']);
        $this->assertFalse($items['quiz']['canStart']);
        $this->assertSame(0, $items['quiz']['policy']['attemptsRemaining']);
    }

    public function test_filters_and_pagination_apply_to_both_sources(): void
    {
        [$student, , $lesson, $exam] = $this->fixture();
        foreach (range(2, 14) as $position) {
            $lesson->trainings()->create(['title_ar' => 'اختبار '.$position, 'title_en' => 'Quiz '.$position, 'type' => 'quiz', 'status' => 'published', 'position' => $position]);
        }
        $this->getJson('/api/me/assessments?type=quiz&page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 14)->assertJsonPath('meta.lastPage', 2);
        $this->getJson('/api/me/assessments?search=Programming&type=exam')->assertOk()->assertJsonCount(1, 'data');
        $exam->update(['available_from' => now()->addDay()]);
        $this->getJson('/api/me/assessments?availability=upcoming')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.blockedReason', 'upcoming');
        $this->getJson('/api/me/assessments?result=passed')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/me/assessments?type=invalid')->assertUnprocessable();
    }

    public function test_reviewed_grades_including_zero_are_consistent_across_student_exam_views(): void
    {
        [$student, $course, , $exam] = $this->fixture();
        $exam->attempts()->create(['user_id' => $student->id, 'score' => 20, 'manual_score' => 85, 'passed' => true, 'answers' => [], 'submitted_at' => now()->subDay()]);
        $latest = $exam->attempts()->create(['user_id' => $student->id, 'score' => 100, 'manual_score' => 0, 'passed' => false, 'answers' => [], 'submitted_at' => now()]);
        $item = collect($this->getJson('/api/me/assessments')->assertOk()->json('data'))->firstWhere('type', 'exam');
        $this->assertEquals(85, $item['bestScore']);
        $this->assertEquals(0, $item['latestScore']);
        $this->getJson('/api/me/assessments/exam/'.$exam->id.'/attempts')->assertOk()->assertJsonPath('data.0.id', $latest->id)->assertJsonPath('data.0.score', 0);
        $this->getJson('/api/courses/'.$course->id.'/assessment')->assertOk()->assertJsonPath('attempts.0.score', 0)->assertJsonPath('attempts.1.score', 85);
        $this->getJson('/api/me/assessment-attempts')->assertOk()->assertJsonPath('data.0.score', 0)->assertJsonPath('meta.summary.bestScore', 85)->assertJsonPath('meta.summary.averageScore', 43);
        $this->getJson('/api/me/learning-results?type=exam')->assertOk()->assertJsonPath('data.0.score', 0);
    }

    public function test_past_results_remain_visible_after_access_expires_or_an_assessment_is_unpublished(): void
    {
        [$student, , , $exam, $quiz] = $this->fixture();
        $exam->attempts()->create(['user_id' => $student->id, 'score' => 60, 'passed' => false, 'answers' => [], 'submitted_at' => now()]);
        $quiz->attempts()->create(['student_id' => $student->id, 'score' => 50, 'status' => 'submitted', 'answers' => [], 'started_at' => now(), 'submitted_at' => now()]);
        $exam->update(['is_published' => false]);
        $quiz->update(['status' => 'draft']);
        $this->getJson('/api/me/assessments')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.canStart', false);
        $student->enrollments()->update(['access_expires_at' => now()->subDay()]);
        $this->getJson('/api/me/assessments')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.blockedReason', 'access_required');
    }

    public function test_drafts_and_prerequisites_control_starting_without_hiding_scores(): void
    {
        [$student, , $lesson, $exam, $quiz] = $this->fixture();
        $exam->update(['due_at' => now()->subMinute()]);
        $exam->drafts()->create(['user_id' => $student->id, 'answers' => [], 'started_at' => now()->subHour(), 'expires_at' => now()->subMinute(), 'current_index' => 0]);
        $previous = $lesson->course->lessons()->create(['slug' => 'previous', 'title' => 'Previous', 'title_en' => 'Previous', 'position' => 2, 'status' => 'published']);
        $lesson->prerequisites()->create(['prerequisite_lesson_id' => $previous->id]);
        $items = collect($this->getJson('/api/me/assessments')->assertOk()->json('data'))->keyBy('type');
        $this->assertTrue($items['exam']['hasDraft']);
        $this->assertTrue($items['exam']['canStart']);
        $this->assertSame('in_progress', $items['exam']['result']);
        $this->assertSame('lesson_locked', $items['quiz']['blockedReason']);
    }

    public function test_attempt_history_is_paginated_and_never_exposes_answers_or_other_users(): void
    {
        [$student, , , $exam] = $this->fixture();
        foreach (range(1, 12) as $score) {
            $exam->attempts()->create(['user_id' => $student->id, 'score' => $score, 'passed' => false, 'answers' => ['secret' => 0], 'submitted_at' => now()]);
        }
        $this->getJson('/api/me/assessments/exam/'.$exam->id.'/attempts?page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 12)->assertDontSee('answers')->assertDontSee('secret');
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->getJson('/api/me/assessments/exam/'.$exam->id.'/attempts')->assertNotFound();
        foreach (['parent', 'teacher', 'admin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->getJson('/api/me/assessments')->assertForbidden();
            $this->getJson('/api/me/assessments/exam/'.$exam->id.'/attempts')->assertForbidden();
        }
    }
}
