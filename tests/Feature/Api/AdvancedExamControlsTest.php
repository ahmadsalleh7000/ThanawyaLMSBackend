<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvancedExamControlsTest extends TestCase
{
    use RefreshDatabase;

    private function exam(bool $practice, array $settings = []): array
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true]);
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);
        $lesson = $course->lessons()->create(['slug' => 'exam', 'title' => 'اختبار', 'title_en' => 'Exam', 'position' => 1]);
        $exam = $practice ? $lesson->trainings()->create($settings + ['type' => 'quiz', 'title_ar' => 'اختبار', 'title_en' => 'Quiz', 'position' => 1, 'status' => 'published', 'passing_score' => 70])
            : Assessment::create($settings + ['course_id' => $course->id, 'is_published' => true, 'duration_minutes' => 10, 'passing_score' => 70]);
        $question = $exam->questions()->create(['type' => 'single_choice', 'topic' => 'Basics', 'topic_ar' => 'أساسيات', 'prompt' => 'Choose', 'prompt_ar' => 'اختر', 'options' => ['Correct', 'Wrong', 'Other'], 'options_ar' => ['صحيح', 'خطأ', 'آخر'], 'correct_option' => 0, 'explanation' => 'Secret explanation', 'explanation_ar' => 'تفسير سري'] + ($practice ? ['is_active' => true] : ['position' => 1]));
        $url = $practice ? "/api/courses/{$course->id}/lessons/exam/practice/{$exam->id}" : "/api/courses/{$course->id}/assessment";
        Sanctum::actingAs($student);

        return [$exam, $question, $url];
    }

    public function test_retry_limits_are_enforced_even_on_direct_submission(): void
    {
        foreach ([false, true] as $practice) {
            [$exam, $question, $url] = $this->exam($practice, ['max_retries' => 0]);
            if (! $practice) {
                $this->postJson($url.'/submit', ['answers' => [$question->id => 0]])->assertUnprocessable();
                $this->postJson($url.'/start')->assertOk();
            }
            $submit = $practice ? $url : $url.'/submit';
            $this->postJson($submit, ['answers' => [$question->id => 0]])->assertOk()->assertJsonPath($practice ? 'data.policy.attemptsRemaining' : 'policy.attemptsRemaining', 0);
            $this->postJson($submit, ['answers' => [$question->id => 0]])->assertUnprocessable();
            if (! $practice) {
                $this->postJson($url.'/start')->assertUnprocessable();
            }
            $this->assertSame(1, $exam->attempts()->count());
        }
    }

    public function test_retry_delays_and_retake_after_passing_apply_to_both_types(): void
    {
        foreach ([false, true] as $practice) {
            [$exam, $question, $url] = $this->exam($practice, ['max_retries' => 2, 'retry_delay_minutes' => 30, 'allow_retake_after_pass' => false]);
            $submit = $practice ? $url : $url.'/submit';
            if (! $practice) {
                $this->postJson($url.'/start')->assertOk();
            }
            $this->postJson($submit, ['answers' => [$question->id => 1]])->assertOk();
            $this->postJson($submit, ['answers' => [$question->id => 0]])->assertUnprocessable();
            $this->travel(31)->minutes();
            if (! $practice) {
                $this->postJson($url.'/start')->assertOk();
            }
            $this->postJson($submit, ['answers' => [$question->id => 0]])->assertOk();
            $this->travel(31)->minutes();
            $this->postJson($submit, ['answers' => [$question->id => 0]])->assertUnprocessable();
            $this->getJson($url)->assertOk()->assertJsonPath($practice ? 'data.policy.blockedReason' : 'policy.blockedReason', 'already_passed');
        }
    }

    public function test_shuffled_options_are_stable_bilingual_and_store_canonical_history(): void
    {
        foreach ([false, true] as $practice) {
            [$exam, $question, $url] = $this->exam($practice, ['randomize_options' => true, 'randomize_questions' => true]);
            $payload = $this->getJson($url)->assertOk()->json($practice ? 'data.questions' : 'questions');
            $this->assertSame($payload, $this->getJson($url)->json($practice ? 'data.questions' : 'questions'));
            $index = array_search('Correct', $payload[0]['options'], true);
            $this->assertSame('صحيح', $payload[0]['optionsAr'][$index]);
            $this->assertArrayNotHasKey('correctOption', $payload[0]);
            if (! $practice) {
                $this->postJson($url.'/start')->assertOk();
            }
            $this->postJson($practice ? $url : $url.'/submit', ['answers' => [$question->id => $index]])->assertOk()->assertJsonPath($practice ? 'data.result.score' : 'score', 100);
            $this->assertSame(0, $exam->attempts()->first()->answers[$question->id]);
        }
    }

    public function test_review_settings_remove_answers_and_explanations_from_responses(): void
    {
        foreach ([false, true] as $practice) {
            [$exam, $question, $url] = $this->exam($practice, ['show_correct_answers' => false, 'show_explanations' => true]);
            if (! $practice) {
                $this->postJson($url.'/start')->assertOk();
            }
            $submit = $practice ? $url : $url.'/submit';
            $this->postJson($submit, ['answers' => [$question->id => 0]])->assertOk()->assertJsonPath($practice ? 'data.result.review' : 'review', [])->assertDontSee('Secret explanation');
            $exam->update(['show_correct_answers' => true, 'show_explanations' => false]);
            if (! $practice) {
                $this->postJson($url.'/start')->assertOk();
            }
            $review = $this->postJson($submit, ['answers' => [$question->id => 1]])->assertOk()->json($practice ? 'data.result.review.0' : 'review.0');
            $this->assertSame(0, $review['correctOption']);
            $this->assertArrayNotHasKey('explanation', $review);
        }
    }

    public function test_disabled_questions_and_required_answers_apply_to_both_types(): void
    {
        foreach ([false, true] as $practice) {
            [$exam, $question, $url] = $this->exam($practice, ['require_all_answers' => true]);
            $disabled = $exam->questions()->create(['type' => 'single_choice', 'topic' => 'Hidden', 'topic_ar' => 'مخفي', 'prompt' => 'Hidden', 'prompt_ar' => 'مخفي', 'options' => ['A', 'B'], 'options_ar' => ['أ', 'ب'], 'correct_option' => 0, 'explanation' => 'A', 'explanation_ar' => 'أ', 'is_active' => true] + ($practice ? [] : ['position' => 2]));
            $practice ? $exam->update(['excluded_question_ids' => [$disabled->id]]) : $disabled->update(['is_active' => false]);
            $this->getJson($url)->assertOk()->assertJsonCount(1, $practice ? 'data.questions' : 'questions');
            if (! $practice) {
                $this->postJson($url.'/start')->assertOk();
            }
            $submit = $practice ? $url : $url.'/submit';
            $this->postJson($submit, ['answers' => []])->assertUnprocessable();
            $this->postJson($submit, ['answers' => [$question->id => 99]])->assertUnprocessable();
            $this->postJson($submit, ['answers' => [$question->id => 0, $disabled->id => 1]])->assertOk()->assertJsonPath($practice ? 'data.result.total' : 'total', 1)->assertJsonPath($practice ? 'data.result.score' : 'score', 100);
        }
    }

    public function test_expired_drafts_keep_their_original_timer_and_grade_saved_answers(): void
    {
        [, $question, $url] = $this->exam(false, ['max_retries' => 1]);
        $expires = $this->postJson($url.'/start')->assertOk()->json('data.expiresAt');
        $this->putJson($url.'/draft', ['answers' => [$question->id => 0], 'current_index' => 0])->assertOk();
        $this->travel(11)->minutes();
        $this->postJson($url.'/start')->assertOk()->assertJsonPath('data.expiresAt', $expires);
        $this->postJson($url.'/submit', ['answers' => [$question->id => 1]])->assertOk()->assertJsonPath('score', 100);
    }

    public function test_sequential_navigation_preserves_previous_answers_and_rejects_jumps(): void
    {
        [$exam, $first, $url] = $this->exam(false, ['allow_question_navigation' => false, 'require_all_answers' => true]);
        $second = $exam->questions()->create(['type' => 'single_choice', 'topic' => 'Next', 'topic_ar' => 'التالي', 'prompt' => 'Next', 'prompt_ar' => 'التالي', 'options' => ['A', 'B'], 'options_ar' => ['أ', 'ب'], 'correct_option' => 0, 'explanation' => 'A', 'explanation_ar' => 'أ', 'position' => 2]);
        $this->postJson($url.'/start')->assertOk();
        $this->putJson($url.'/draft', ['answers' => [], 'current_index' => 1])->assertUnprocessable();
        $this->putJson($url.'/draft', ['answers' => [$first->id => 0], 'current_index' => 2])->assertUnprocessable();
        $this->putJson($url.'/draft', ['answers' => [$first->id => 0], 'current_index' => 1])->assertOk();
        $this->putJson($url.'/draft', ['answers' => [$first->id => 1], 'current_index' => 0])->assertUnprocessable();
        $this->postJson($url.'/submit', ['answers' => [$first->id => 1, $second->id => 0]])->assertOk()->assertJsonPath('score', 100);
    }

    public function test_deadline_caps_the_timer_but_allows_finalizing_saved_work(): void
    {
        [$exam, $question, $url] = $this->exam(false, ['due_at' => now()->addMinutes(2)]);
        $this->postJson($url.'/start')->assertOk()->assertJsonPath('data.expiresAt', $exam->due_at->toISOString());
        $this->putJson($url.'/draft', ['answers' => [$question->id => 0], 'current_index' => 0])->assertOk();
        $this->travel(3)->minutes();
        $this->getJson($url)->assertOk();
        $this->postJson($url.'/submit', ['answers' => [$question->id => 1]])->assertOk()->assertJsonPath('score', 100);
        $this->postJson($url.'/start')->assertGone();
    }
}
