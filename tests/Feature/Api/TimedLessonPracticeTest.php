<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimedLessonPracticeTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(array $settings = []): array
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true]);
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);
        $lesson = $course->lessons()->create(['slug' => 'practice', 'title' => 'تدريب', 'title_en' => 'Practice', 'position' => 1, 'status' => 'published']);
        $training = $lesson->trainings()->create($settings + ['title_ar' => 'اختبار', 'title_en' => 'Quiz', 'type' => 'quiz', 'position' => 1, 'status' => 'published', 'duration_minutes' => 5, 'passing_score' => 70]);
        $question = $this->question($training, ['type' => 'single_choice', 'prompt' => 'Choose', 'prompt_ar' => 'اختر', 'topic' => 'Basics', 'topic_ar' => 'أساسيات', 'options' => ['Yes', 'No'], 'options_ar' => ['نعم', 'لا'], 'correct_option' => 0, 'is_active' => true]);
        Sanctum::actingAs($student);

        return [$student, $course, $lesson, $training, $question, "/api/courses/{$course->id}/lessons/practice/practice/{$training->id}"];
    }

    private function question($quiz, array $data)
    {
        return $quiz->questions()->create($data + ['topic' => 'Basics', 'topic_ar' => 'أساسيات', 'options_ar' => [], 'explanation' => 'Explanation', 'explanation_ar' => 'توضيح']);
    }

    public function test_timed_practice_requires_start_and_restores_only_the_students_own_draft(): void
    {
        [$student, $course, , $quiz, $question, $url] = $this->fixture();
        $this->postJson($url, ['answers' => [$question->id => 0]])->assertUnprocessable();
        $draft = $this->postJson($url.'/start')->assertOk()->json('data');
        $this->putJson($url.'/draft', ['draft_id' => $draft['id'], 'answers' => [$question->id => 1], 'current_index' => 0])->assertOk();
        $this->travel(1)->minutes();
        $this->postJson($url.'/start')->assertOk()->assertJsonPath('data.expiresAt', $draft['expiresAt']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.draft.answers.'.$question->id, 1)->assertDontSee('correctOption');
        $hub = collect($this->getJson('/api/me/assessments')->assertOk()->json('data'))->firstWhere('type', 'quiz');
        $this->assertTrue($hub['hasDraft']);
        $this->assertSame('in_progress', $hub['result']);
        $this->assertSame(5, $hub['durationMinutes']);
        $other = User::factory()->create(['role' => 'student']);
        $other->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);
        Sanctum::actingAs($other);
        $this->getJson($url)->assertOk()->assertJsonPath('data.draft', null);
        $this->putJson($url.'/draft', ['draft_id' => $draft['id'], 'answers' => [], 'current_index' => 0])->assertNotFound();
        $this->postJson($url, ['draft_id' => $draft['id'], 'answers' => []])->assertUnprocessable();
        $this->assertSame(1, $quiz->drafts()->where('student_id', $student->id)->count());
    }

    public function test_lesson_quizzes_grade_multiple_select_and_text_answers_with_shuffled_options(): void
    {
        [, , , $quiz, $single, $url] = $this->fixture(['randomize_options' => true, 'require_all_answers' => true]);
        $multi = $this->question($quiz, ['type' => 'multiple_select', 'prompt' => 'Select two', 'prompt_ar' => 'اختر اثنين', 'options' => ['A', 'B', 'C'], 'correct_options' => [0, 2], 'is_active' => true]);
        $short = $this->question($quiz, ['type' => 'short_answer', 'prompt' => 'Name', 'prompt_ar' => 'اسم', 'options' => [], 'correct_answer' => 'Hello world', 'is_active' => true]);
        $blank = $this->question($quiz, ['type' => 'fill_in_blank', 'prompt' => 'Fill', 'prompt_ar' => 'أكمل', 'options' => [], 'correct_answer' => 'Python', 'is_active' => true]);
        $questions = collect($this->getJson($url)->assertOk()->json('data.questions'))->keyBy('id');
        $draftId = $this->postJson($url.'/start')->assertOk()->json('data.id');
        $answers = [$single->id => array_search('Yes', $questions[$single->id]['options']), $multi->id => [array_search('C', $questions[$multi->id]['options']), array_search('A', $questions[$multi->id]['options'])], $short->id => '  HELLO   world ', $blank->id => 'python'];
        $this->postJson($url, ['draft_id' => $draftId, 'answers' => array_replace($answers, [$multi->id => ['garbage']])])->assertUnprocessable();
        $result = $this->postJson($url, ['draft_id' => $draftId, 'answers' => $answers])->assertOk()->assertJsonPath('data.result.score', 100)->assertJsonPath('data.result.correct', 4);
        $stored = $quiz->attempts()->first()->answers;
        $this->assertEqualsCanonicalizing([0, 2], $stored[$multi->id]);
        $this->assertSame(0, $stored[$single->id]);
        $this->assertTrue($result->json('data.result.review.1.correct'));
    }

    public function test_sequential_practice_locks_prior_answers_and_saves_position(): void
    {
        [, , , $quiz, $first, $url] = $this->fixture(['allow_question_navigation' => false, 'require_all_answers' => true]);
        $second = $this->question($quiz, ['type' => 'single_choice', 'prompt' => 'Next', 'prompt_ar' => 'التالي', 'options' => ['A', 'B'], 'correct_option' => 0, 'is_active' => true]);
        $draftId = $this->postJson($url.'/start')->assertOk()->json('data.id');
        $this->putJson($url.'/draft', ['answers' => [], 'current_index' => 1])->assertUnprocessable();
        $this->putJson($url.'/draft', ['answers' => [$first->id => 0], 'current_index' => 2])->assertUnprocessable();
        $this->putJson($url.'/draft', ['answers' => [$first->id => 0], 'current_index' => 1])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('data.draft.currentIndex', 1);
        $this->putJson($url.'/draft', ['answers' => [$first->id => 1], 'current_index' => 0])->assertUnprocessable();
        $this->postJson($url, ['draft_id' => $draftId, 'answers' => [$first->id => 1, $second->id => 0]])->assertOk()->assertJsonPath('data.result.score', 100);
        $nextDraftId = $this->postJson($url.'/start')->assertOk()->json('data.id');
        $this->assertNotSame($draftId, $nextDraftId);
        $this->putJson($url.'/draft', ['draft_id' => $draftId, 'answers' => [$first->id => 1], 'current_index' => 0])->assertNotFound();
    }

    public function test_worker_finalizes_closed_browser_attempts_once_and_allows_safe_submission_replay(): void
    {
        [$student, $course, $lesson, $quiz, $question, $url] = $this->fixture(['available_until' => now()->addMinutes(2), 'require_all_answers' => true, 'max_retries' => 0]);
        $exam = Assessment::create(['course_id' => $course->id, 'duration_minutes' => 1, 'is_published' => true, 'max_retries' => 0]);
        $examQuestion = $exam->questions()->create(['type' => 'single_choice', 'prompt' => 'Exam', 'prompt_ar' => 'امتحان', 'topic' => 'Basics', 'topic_ar' => 'أساسيات', 'options' => ['Yes', 'No'], 'options_ar' => ['نعم', 'لا'], 'correct_option' => 0, 'explanation' => 'Yes', 'explanation_ar' => 'نعم', 'position' => 1]);
        $student->lessonCompletions()->create(['lesson_id' => $lesson->id, 'completed_at' => now()]);
        $examUrl = "/api/courses/{$course->id}/assessment";
        $examDraft = $this->postJson($examUrl.'/start')->assertOk()->json('data');
        $quizDraft = $this->postJson($url.'/start')->assertOk()->json('data');
        $this->assertSame($quiz->available_until->toISOString(), $quizDraft['expiresAt']);
        $this->putJson($examUrl.'/draft', ['answers' => [$examQuestion->id => 0], 'current_index' => 0])->assertOk();
        $this->putJson($url.'/draft', ['answers' => [$question->id => 0], 'current_index' => 0])->assertOk();
        $this->artisan('assessments:finalize-expired')->expectsOutput('Finalized 0 expired assessments.')->assertSuccessful();
        $this->travel(3)->minutes();
        $this->putJson($url.'/draft', ['answers' => [$question->id => 1], 'current_index' => 0])->assertUnprocessable();
        $this->artisan('assessments:finalize-expired')->expectsOutput('Finalized 2 expired assessments.')->assertSuccessful();
        $this->artisan('assessments:finalize-expired')->expectsOutput('Finalized 0 expired assessments.')->assertSuccessful();
        $this->assertSame(1, $exam->attempts()->count());
        $this->assertSame(1, $quiz->attempts()->count());
        $this->assertSame(0, $quiz->drafts()->count());
        $this->assertTrue($exam->attempts()->first()->submitted_at->eq($examDraft['expiresAt']));
        $this->assertNotNull($student->lessonWorkspaceStates()->where('lesson_id', $lesson->id)->first()->practice_passed_at);
        $this->assertDatabaseHas('certificates', ['user_id' => $student->id, 'course_id' => $course->id]);
        $this->postJson($examUrl.'/submit', ['draft_id' => $examDraft['id'], 'answers' => [$examQuestion->id => 1]])->assertOk()->assertJsonPath('score', 100)->assertJsonPath('answers.'.$examQuestion->id, 0);
        $this->postJson($url, ['draft_id' => $quizDraft['id'], 'answers' => [$question->id => 1]])->assertOk()->assertJsonPath('data.result.score', 100);
        $this->assertSame(1, $exam->attempts()->count());
        $this->assertSame(1, $quiz->attempts()->count());
        $this->postJson($url.'/start')->assertGone();
    }

    public function test_untimed_drafts_resume_and_are_not_expired_by_the_worker(): void
    {
        [, , , $quiz, $question, $url] = $this->fixture(['duration_minutes' => null]);
        $this->postJson($url.'/start')->assertOk()->assertJsonPath('data.expiresAt', null);
        $this->putJson($url.'/draft', ['answers' => [$question->id => 0], 'current_index' => 0])->assertOk();
        $this->travel(1)->days();
        $this->artisan('assessments:finalize-expired')->expectsOutput('Finalized 0 expired assessments.')->assertSuccessful();
        $this->getJson($url)->assertOk()->assertJsonPath('data.draft.answers.'.$question->id, 0);
        $this->assertSame(0, $quiz->attempts()->count());
    }

    public function test_active_question_snapshot_survives_bank_edits_and_disabled_questions(): void
    {
        [, , , $quiz, $question, $url] = $this->fixture(['randomize_options' => true]);
        $delivered = $this->getJson($url)->assertOk()->json('data.questions');
        $selected = array_search('Yes', $delivered[0]['options'], true);
        $draftId = $this->postJson($url.'/start')->assertOk()->json('data.id');
        $this->putJson($url.'/draft', ['answers' => [$question->id => $selected], 'current_index' => 0])->assertOk();
        $question->update(['is_active' => false, 'correct_option' => 1, 'prompt' => 'Changed']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.questions', $delivered)->assertDontSee('question_snapshot');
        $this->postJson($url.'/start')->assertOk()->assertJsonPath('data.id', $draftId);
        $this->travel(6)->minutes();
        $this->artisan('assessments:finalize-expired')->expectsOutput('Finalized 1 expired assessments.')->assertSuccessful();
        $this->assertEquals(100, $quiz->attempts()->first()->score);
        $this->postJson($url, ['draft_id' => $draftId, 'answers' => []])->assertOk()->assertJsonPath('data.result.score', 100)->assertJsonPath('data.result.review.0.correct', true);
    }

    public function test_expired_practice_grades_saved_partial_work_even_when_all_answers_are_required(): void
    {
        [, , , $quiz, $first, $url] = $this->fixture(['require_all_answers' => true, 'show_correct_answers' => false]);
        $second = $this->question($quiz, ['type' => 'single_choice', 'prompt' => 'Next', 'prompt_ar' => 'التالي', 'options' => ['A', 'B'], 'correct_option' => 0]);
        $draftId = $this->postJson($url.'/start')->assertOk()->json('data.id');
        $this->putJson($url.'/draft', ['answers' => [$first->id => 0], 'current_index' => 0])->assertOk();
        $this->travel(6)->minutes();
        $this->postJson($url, ['draft_id' => $draftId, 'answers' => [$first->id => 0, $second->id => 0]])->assertOk()->assertJsonPath('data.result.score', 50)->assertJsonPath('data.result.review', []);
        $this->assertSame([$first->id => 0], $quiz->attempts()->first()->answers);
    }
}
