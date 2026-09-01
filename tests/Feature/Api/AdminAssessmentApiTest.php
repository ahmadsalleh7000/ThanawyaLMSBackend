<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAssessmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_publish_and_report_on_an_assessment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course();
        Sanctum::actingAs($admin);
        $this->putJson('/api/admin/courses/'.$course->id.'/assessment', ['duration_minutes' => 12, 'passing_score' => 70])
            ->assertCreated()->assertJsonPath('data.isPublished', false);
        $question = $this->postJson('/api/admin/courses/'.$course->id.'/assessment/questions', $this->question())
            ->assertCreated()->json('data');
        $this->postJson('/api/admin/courses/'.$course->id.'/assessment/publish')->assertOk()->assertJsonPath('data.isPublished', true);
        $this->patchJson('/api/admin/courses/'.$course->id.'/assessment/questions/'.$question['id'], ['prompt' => 'Changed?'])->assertConflict();

        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);
        Sanctum::actingAs($student);
        $this->postJson('/api/courses/'.$course->id.'/assessment/submit', ['answers' => [(string) $question['id'] => 0]])
            ->assertOk()->assertJsonPath('score', 100)->assertJsonPath('passed', true);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/courses/'.$course->id.'/assessment/attempts')->assertOk()
            ->assertJsonPath('data.summary.attempts', 1)->assertJsonPath('data.summary.averageScore', 100)
            ->assertJsonPath('data.summary.passRate', 100)->assertJsonPath('data.attempts.0.student.id', $student->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'assessment.published']);
    }

    public function test_questions_require_matching_bilingual_options_and_valid_correct_answer(): void
    {
        $course = $this->course('validation-course');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson('/api/admin/courses/'.$course->id.'/assessment', ['duration_minutes' => 10, 'passing_score' => 60])->assertCreated();
        $invalid = array_merge($this->question(), ['options_ar' => ['واحد']]);
        $this->postJson('/api/admin/courses/'.$course->id.'/assessment/questions', $invalid)->assertUnprocessable();
        $invalid = array_merge($this->question(), ['correct_option' => 4]);
        $this->postJson('/api/admin/courses/'.$course->id.'/assessment/questions', $invalid)->assertUnprocessable();
        $this->assertDatabaseCount('assessment_questions', 0);
    }

    public function test_non_admin_cannot_manage_assessments(): void
    {
        $course = $this->course('forbidden-assessment');
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->putJson('/api/admin/courses/'.$course->id.'/assessment', ['duration_minutes' => 10, 'passing_score' => 70])->assertForbidden();
    }

    private function question(): array
    {
        return ['topic' => 'Basics', 'topic_ar' => 'الأساسيات', 'prompt' => 'Which is correct?', 'prompt_ar' => 'ما الإجابة الصحيحة؟', 'options' => ['Correct', 'Wrong'], 'options_ar' => ['صحيحة', 'خطأ'], 'correct_option' => 0, 'explanation' => 'The first answer is correct.', 'explanation_ar' => 'الإجابة الأولى صحيحة.', 'position' => 1];
    }

    private function course(string $id = 'assessment-admin-course'): Course
    {
        return Course::create(['id' => $id, 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
    }
}
