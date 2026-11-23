<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentLearningResultsTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['title' => 'دورة البرمجة', 'title_en' => 'Programming course']);
        $lesson = $course->lessons()->create(['slug' => 'intro', 'title' => 'مقدمة', 'title_en' => 'Introduction', 'position' => 1]);
        $exam = Assessment::create(['course_id' => $course->id, 'passing_score' => 70]);
        $exam->attempts()->create(['user_id' => $student->id, 'answers' => ['secret' => 1], 'score' => 50, 'manual_score' => 85, 'manual_feedback' => 'Reviewed by teacher.', 'passed' => true, 'submitted_at' => now()->subHours(3)]);
        $quiz = $lesson->trainings()->create(['title_ar' => 'اختبار المقدمة', 'title_en' => 'Intro quiz', 'type' => 'quiz', 'position' => 1]);
        $quiz->attempts()->create(['student_id' => $student->id, 'answers' => ['secret' => 2], 'score' => 40, 'status' => 'submitted', 'started_at' => now()->subHours(2), 'submitted_at' => now()->subHour()]);
        $assignment = $lesson->assignments()->create(['title' => 'مهمة المقدمة', 'title_en' => 'Intro assignment']);
        $submission = $assignment->submissions()->create(['user_id' => $student->id, 'body' => 'Private answer', 'file_path' => 'secret/file.pdf', 'score' => 0, 'feedback' => 'Explain the answer.', 'status' => 'graded', 'submitted_at' => now()]);
        Sanctum::actingAs($student);

        return [$student, $course, $exam, $quiz, $assignment, $submission];
    }

    public function test_results_merge_all_three_sources_and_use_manual_grades_without_answer_leaks(): void
    {
        [, $course] = $this->fixtures();
        $response = $this->getJson('/api/me/learning-results')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(3, 'data')->assertJsonPath('data.0.type', 'assignment')->assertJsonPath('data.0.score', 0)
            ->assertJsonPath('data.1.type', 'quiz')->assertJsonPath('data.1.result', 'needs_review')
            ->assertJsonPath('data.2.type', 'exam')->assertJsonPath('data.2.score', 85)->assertJsonPath('data.2.feedback', 'Reviewed by teacher.')
            ->assertJsonPath('meta.summary.total', 3)->assertJsonPath('meta.summary.passed', 1)->assertJsonPath('meta.summary.averageScore', 41.7)
            ->assertDontSee('secret')->assertDontSee('Private answer');
        $this->assertCount(3, array_unique(array_column($response->json('data'), 'id')));
        $this->assertStringStartsWith('/my-assessments/assignments?', $response->json('data.0.url'));
        $this->assertSame($course->id, $response->json('meta.courses.0.id'));
    }

    public function test_results_are_private_and_do_not_include_unsubmitted_quiz_drafts(): void
    {
        [, , $exam, $quiz, $assignment] = $this->fixtures();
        $other = User::factory()->create(['role' => 'student']);
        $exam->attempts()->create(['answers' => [], 'user_id' => $other->id, 'score' => 100, 'passed' => true, 'submitted_at' => now()]);
        $quiz->attempts()->create(['answers' => [], 'student_id' => $other->id, 'score' => 100, 'status' => 'passed', 'started_at' => now(), 'submitted_at' => now()]);
        $assignment->submissions()->create(['user_id' => $other->id, 'score' => 100, 'status' => 'graded', 'feedback' => 'Other feedback', 'submitted_at' => now()]);
        Sanctum::actingAs($other);
        $this->getJson('/api/me/learning-results')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('meta.summary.averageScore', 100)->assertDontSee('Explain the answer.');
        $new = User::factory()->create(['role' => 'student']);
        $quiz->attempts()->create(['answers' => [], 'student_id' => $new->id, 'status' => 'in_progress', 'started_at' => now()]);
        Sanctum::actingAs($new);
        $this->getJson('/api/me/learning-results')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.summary.averageScore', null)->assertJsonCount(0, 'meta.courses');
    }

    public function test_filters_and_pagination_apply_to_the_combined_timeline(): void
    {
        [$student, $course, $exam] = $this->fixtures();
        $this->getJson('/api/me/learning-results?type=quiz&result=needs_review&search=Intro&course_id='.$course->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.summary.total', 1);
        $this->getJson('/api/me/learning-results?result=graded')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'assignment');
        $this->getJson('/api/me/learning-results?result=passed')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'exam');
        foreach (range(1, 14) as $number) {
            $exam->attempts()->create(['answers' => [], 'user_id' => $student->id, 'score' => $number, 'passed' => false, 'submitted_at' => now()->subDays($number)]);
        }
        $this->getJson('/api/me/learning-results?page=2')->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('meta.total', 17)->assertJsonPath('meta.lastPage', 2);
        $this->getJson('/api/me/learning-results?type=invalid')->assertUnprocessable();
        $this->getJson('/api/me/learning-results?result=invalid')->assertUnprocessable();
        $this->getJson('/api/me/learning-results?page=0')->assertUnprocessable();
    }

    public function test_ungraded_submissions_have_no_score_and_history_survives_loss_of_access(): void
    {
        [, $course, , , $assignment, $submission] = $this->fixtures();
        $submission->update(['status' => 'returned', 'score' => 99]);
        $course->update(['is_published' => false, 'archived_at' => now()]);
        $this->getJson('/api/me/learning-results?type=assignment')->assertOk()->assertJsonPath('data.0.result', 'returned')->assertJsonPath('data.0.score', null)->assertJsonPath('meta.summary.averageScore', null);
        $submission->update(['status' => 'resubmitted', 'score' => null]);
        $this->getJson('/api/me/learning-results?result=awaiting_review')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.summary.awaitingReview', 1);
        $assignment->update(['is_published' => false]);
        $this->getJson('/api/me/learning-results')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_non_students_cannot_use_the_combined_results_endpoint(): void
    {
        foreach (['parent', 'teacher', 'admin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->getJson('/api/me/learning-results')->assertForbidden();
        }
    }
}
