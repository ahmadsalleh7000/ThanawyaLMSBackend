<?php

namespace Tests\Feature\Web;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\QuestionBankQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedQuestionAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private function fields(array $fields = []): array
    {
        return $fields + ['type' => 'multiple_select', 'difficulty' => 'medium', 'topic' => 'Basics', 'topic_ar' => 'أساسيات', 'prompt' => 'Choose', 'prompt_ar' => 'اختر', 'options_text' => "A\nB\nC", 'options_ar_text' => "أ\nب\nج", 'correct_options_text' => '1, 3', 'explanation' => 'Explanation', 'explanation_ar' => 'توضيح'];
    }

    public function test_admin_authors_advanced_bank_questions_and_imports_all_answer_fields(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['multiple_select', 'short_answer', 'fill_in_blank'] as $type) {
            $this->post(route('admin.question-bank.store'), $this->fields(['type' => $type, 'correct_answer' => 'Python']))->assertRedirect()->assertSessionHasNoErrors();
        }
        $questions = QuestionBankQuestion::orderBy('id')->get();
        $this->assertSame([0, 2], $questions[0]->correct_options);
        $this->assertNull($questions[0]->correct_answer);
        $this->assertSame('Python', $questions[1]->correct_answer);
        $this->assertSame([], $questions[1]->options);
        $course = Course::factory()->create();
        $exam = Assessment::create(['course_id' => $course->id]);
        $this->post(route('admin.question-bank.import', $course), ['question_ids' => $questions->modelKeys()])->assertRedirect();
        $this->assertSame([0, 2], $exam->questions()->first()->correct_options);
        $this->assertSame('Python', $exam->questions()->where('type', 'short_answer')->first()->correct_answer);
        $this->get(route('admin.question-bank.index', ['type' => 'short_answer']))->assertOk()->assertSee('إجابة قصيرة');
        $this->get(route('admin.question-bank.index', ['view' => 'grid']))->assertOk();
    }

    public function test_admin_can_edit_imported_advanced_questions_and_change_answer_type_cleanly(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $course = Course::factory()->create();
        $exam = Assessment::create(['course_id' => $course->id]);
        $this->post(route('admin.questions.store', $course), $this->fields(['position' => 1]))->assertRedirect()->assertSessionHasNoErrors();
        $question = $exam->questions()->firstOrFail();
        $this->get(route('admin.questions.edit', [$course, $question]))->assertOk()->assertSee('name="correct_options_text"', false);
        $this->put(route('admin.questions.update', [$course, $question]), $this->fields(['position' => 1, 'type' => 'short_answer', 'correct_answer' => 'Python']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($question->fresh()->correct_options);
        $this->assertNull($question->fresh()->correct_option);
        $this->assertSame('Python', $question->fresh()->correct_answer);
    }

    public function test_invalid_correct_answer_indices_and_missing_text_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['0, 1', '1, 4', 'foo'] as $indices) {
            $this->post(route('admin.question-bank.store'), $this->fields(['correct_options_text' => $indices]))->assertUnprocessable();
        }
        $this->post(route('admin.question-bank.store'), $this->fields(['type' => 'short_answer']))->assertSessionHasErrors('correct_answer');
        $this->assertDatabaseCount('question_bank_questions', 0);
    }
}
