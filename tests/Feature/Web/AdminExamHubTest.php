<?php

namespace Tests\Feature\Web;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\QuestionBankQuiz;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminExamHubTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor']));
        $course = Course::factory()->create(['title' => 'دورة البرمجة', 'title_en' => 'Programming course']);
        $lesson = $course->lessons()->create(['slug' => 'intro', 'title' => 'مقدمة البرمجة', 'title_en' => 'Introduction', 'position' => 1]);
        $exam = Assessment::create(['course_id' => $course->id, 'max_retries' => 0, 'is_published' => true]);
        $quiz = QuestionBankQuiz::create(['name_ar' => 'قالب الأساسيات', 'name_en' => 'Basics template', 'type' => 'training', 'is_active' => true, 'passing_score' => 70]);
        $training = $lesson->trainings()->create(['title_ar' => 'تدريب المقدمة', 'title_en' => 'Intro quiz', 'type' => 'quiz', 'status' => 'draft', 'question_bank_quiz_id' => $quiz->id, 'position' => 1, 'max_retries' => 2]);

        return [$course, $exam, $training, $quiz];
    }

    public function test_all_sources_are_listed_with_controls_and_sidebar_navigation(): void
    {
        [$course, , $training] = $this->fixtures();
        $response = $this->get(route('admin.exams.index'))->assertOk()->assertSee('الاختبارات والامتحانات')->assertSee('تدريب المقدمة')->assertSee('قالب الأساسيات')->assertSee('دورة البرمجة')->assertSee('محاولة واحدة فقط')->assertSee('2 مرات إعادة');
        foreach (['assessment', 'training', 'template'] as $kind) {
            $response->assertSee('data-exam-kind="'.$kind.'"', false);
        }
        $response->assertSee(route('admin.learning-paths.trainings.settings', $training), false)->assertSee(route('admin.question-bank.assessment', $course), false)->assertSee(route('admin.exams.training-attempts', $training), false);
        $this->assertSame(3, $response->viewData('records')->total());
    }

    public function test_filters_apply_to_the_combined_listing(): void
    {
        [$course, , $training] = $this->fixtures();
        $other = Course::factory()->create(['title' => 'دورة أخرى']);
        Assessment::create(['course_id' => $other->id]);
        $this->get(route('admin.exams.index', ['type' => 'training', 'search' => 'Intro']))->assertOk()->assertSee('تدريب المقدمة')->assertDontSee('data-exam-kind="assessment"', false)->assertDontSee('data-exam-kind="template"', false);
        $response = $this->get(route('admin.exams.index', ['course_id' => $course->id]))->assertOk();
        $this->assertSame(3, $response->viewData('records')->total());
        $this->get(route('admin.exams.index', ['status' => 'published']))->assertOk()->assertDontSee('تدريب المقدمة')->assertSee('قالب الأساسيات');
        $this->get(route('admin.exams.index', ['type' => 'template', 'course_id' => $course->id]))->assertOk()->assertSee('قالب الأساسيات')->assertDontSee('data-exam-kind="training"', false);
        $training->update(['status' => 'archived']);
        $this->get(route('admin.exams.index', ['status' => 'archived']))->assertOk()->assertSee('استعادة كمسودة')->assertDontSee('data-exam-kind="assessment"', false)->assertDontSee('data-exam-kind="template"', false);
        $this->get(route('admin.exams.index', ['search' => 'no-such-exam']))->assertOk()->assertSee('لا توجد اختبارات مطابقة');
        $this->get(route('admin.exams.index', ['type' => 'invalid']))->assertSessionHasErrors('type');
    }

    public function test_pagination_and_exact_saved_quiz_links_work(): void
    {
        [, , , $quiz] = $this->fixtures();
        foreach (range(1, 18) as $number) {
            QuestionBankQuiz::create(['name_ar' => 'Other template '.$number, 'type' => 'training', 'is_active' => true]);
        }
        $response = $this->get(route('admin.exams.index', ['type' => 'template', 'page' => 2]))->assertOk();
        $records = $response->viewData('records');
        $this->assertSame(19, $records->total());
        $this->assertCount(4, $records->items());
        $this->assertStringContainsString('type=template', $records->url(1));
        $this->get(route('admin.question-bank.index', ['quiz_id' => $quiz->id]))->assertOk()->assertSee('قالب الأساسيات')->assertSee('data-quiz-auto-open', false)->assertDontSee('Other template 18');
    }

    public function test_unit_quizzes_are_included_and_filter_by_course(): void
    {
        [$course] = $this->fixtures();
        $unit = Unit::create(['course_id' => $course->id, 'slug' => 'unit', 'title_ar' => 'وحدة جديدة', 'title_en' => 'New unit', 'position' => 1]);
        $unit->trainings()->create(['title_ar' => 'اختبار الوحدة', 'title_en' => 'Unit quiz', 'type' => 'quiz', 'position' => 1]);
        $this->get(route('admin.exams.index', ['course_id' => $course->id, 'type' => 'training']))->assertOk()->assertSee('اختبار الوحدة')->assertSee('وحدة جديدة');
    }

    public function test_results_are_scoped_to_submitted_attempts_of_the_selected_quiz(): void
    {
        [, , $training] = $this->fixtures();
        $student = User::factory()->create(['name' => 'Quiz Student']);
        foreach ([['passed', 100], ['submitted', 50]] as [$status, $score]) {
            $training->attempts()->create(['started_at' => now()->subMinutes(5), 'student_id' => $student->id, 'score' => $score, 'status' => $status, 'submitted_at' => now()]);
        }
        $training->attempts()->create(['started_at' => now()->subMinutes(5), 'student_id' => $student->id, 'score' => 0, 'status' => 'in_progress']);
        $response = $this->get(route('admin.exams.training-attempts', $training))->assertOk()->assertSee('Quiz Student')->assertSee('75%')->assertSee('50%');
        $this->assertSame(2, $response->viewData('summary')['attempts']);
        $this->assertSame(2, $response->viewData('attempts')->total());
        $this->get(route('admin.exams.training-attempts', ['training' => $training, 'search' => 'missing-student']))->assertOk()->assertDontSee('Quiz Student')->assertSee('لا توجد محاولات لهذا الطالب');
    }

    public function test_publish_controls_keep_existing_guards_and_return_to_the_hub(): void
    {
        [$course, $exam, $training] = $this->fixtures();
        $hub = route('admin.exams.index', ['type' => 'assessment']);
        $this->from($hub)->post(route('admin.assessment.state', $course))->assertRedirect($hub);
        $this->assertFalse($exam->fresh()->is_published);
        $this->from($hub)->post(route('admin.assessment.state', $course))->assertUnprocessable();
        $this->from($hub)->post(route('admin.learning-paths.state', ['training', $training->id]), ['status' => 'published'])->assertRedirect($hub)->assertSessionHasErrors('status');
        $this->assertSame('draft', $training->fresh()->status);
    }

    public function test_students_and_staff_without_content_permission_are_denied(): void
    {
        [, , $training] = $this->fixtures();
        foreach ([['student', null], ['admin', 'finance_reviewer']] as [$role, $adminRole]) {
            $this->actingAs(User::factory()->create(['role' => $role, 'admin_role' => $adminRole]));
            $this->get(route('admin.exams.index'))->assertForbidden();
            $this->get(route('admin.exams.training-attempts', $training))->assertForbidden();
        }
    }
}
