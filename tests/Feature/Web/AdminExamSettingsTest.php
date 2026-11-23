<?php

namespace Tests\Feature\Web;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminExamSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_configure_and_toggle_exam_questions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();
        $assessment = Assessment::create(['course_id' => $course->id]);
        $question = $assessment->questions()->create(['topic' => 'Basics', 'topic_ar' => 'أساسيات', 'prompt' => 'Choose', 'prompt_ar' => 'اختر', 'options' => ['A', 'B'], 'options_ar' => ['أ', 'ب'], 'correct_option' => 0, 'explanation' => 'A', 'explanation_ar' => 'أ', 'position' => 1]);
        $this->actingAs($admin)->get(route('admin.question-bank.assessment', $course))->assertOk()->assertSee('name="max_retries"', false)->assertSee('name="randomize_options"', false);
        $this->post(route('admin.assessment.delivery', $course), ['max_retries' => 0, 'randomize_options' => true, 'show_correct_answers' => false, 'retry_delay_minutes' => 15, 'allow_question_navigation' => false])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, $assessment->fresh()->max_retries);
        $this->assertFalse($assessment->fresh()->show_correct_answers);
        $this->post(route('admin.questions.toggle', [$course, $question]))->assertRedirect();
        $this->assertFalse($question->fresh()->is_active);
        $this->post(route('admin.assessment.state', $course))->assertUnprocessable();
        $this->post(route('admin.questions.toggle', [$course, $question]))->assertRedirect();
        $this->post(route('admin.assessment.state', $course))->assertRedirect();
        $this->post(route('admin.assessment.delivery', $course), ['max_retries' => 3])->assertConflict();
        $this->post(route('admin.questions.toggle', [$course, $question]))->assertConflict();
    }

    public function test_training_settings_are_scoped_to_the_quiz_and_protected_while_published(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();
        $lesson = $course->lessons()->create(['slug' => 'quiz', 'title' => 'Quiz', 'title_en' => 'Quiz', 'position' => 1]);
        $training = $lesson->trainings()->create(['type' => 'quiz', 'title_ar' => 'اختبار', 'title_en' => 'Quiz', 'position' => 1, 'status' => 'draft']);
        $question = $training->questions()->create(['topic' => 'Basics', 'topic_ar' => 'أساسيات', 'prompt' => 'Choose', 'prompt_ar' => 'اختر', 'options' => ['A', 'B'], 'options_ar' => ['أ', 'ب'], 'correct_option' => 0, 'explanation' => 'A', 'explanation_ar' => 'أ']);
        $this->actingAs($admin)->get(route('admin.learning-paths.trainings.settings', $training))->assertOk()->assertSee('name="enabled_question_ids[]"', false)->assertSee('name="duration_minutes"', false)->assertSee('name="allow_question_navigation"', false);
        $url = route('admin.learning-paths.trainings.settings.update', $training);
        $this->put($url, ['passing_score' => 80, 'max_retries' => 2, 'enabled_question_ids' => [], 'duration_minutes' => 15, 'allow_question_navigation' => false])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(15, $training->fresh()->duration_minutes);
        $this->assertFalse($training->fresh()->allow_question_navigation);
        $this->assertSame([$question->id], $training->fresh()->excluded_question_ids);
        $this->assertTrue($question->fresh()->is_active);
        $this->put($url, ['passing_score' => 80, 'enabled_question_ids' => [$question->id], 'max_retries' => -1])->assertSessionHasErrors('max_retries');
        $this->put($url, ['passing_score' => 80, 'duration_minutes' => 0])->assertSessionHasErrors('duration_minutes');
        $this->put($url, ['passing_score' => 80, 'enabled_question_ids' => [$question->id]])->assertRedirect()->assertSessionHasNoErrors();
        $training->update(['status' => 'published']);
        $this->put($url, ['passing_score' => 70, 'enabled_question_ids' => []])->assertConflict();
        $this->actingAs(User::factory()->create(['role' => 'student']))->get(route('admin.learning-paths.trainings.settings', $training))->assertForbidden();
    }
}
