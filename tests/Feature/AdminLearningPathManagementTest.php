<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\QuestionBankQuestion;
use App\Models\QuestionBankQuiz;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminLearningPathManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_editor_can_build_an_ordered_path_from_one_screen(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor', 'is_active' => true]);
        $term = AcademicTerm::with('academicSubjects.subject')->firstOrFail();
        $offering = $term->academicSubjects->firstOrFail();
        $course = Course::create([
            'id' => 'managed-learning-path', 'title' => 'مسار منظم', 'title_en' => 'Managed path',
            'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => $offering->subject->name_en,
            'scope' => 'academic', 'academic_subject_id' => $offering->id, 'position' => 1,
            'grade' => $term->grade->code, 'semester' => $term->code, 'level' => 'Beginner',
            'duration_minutes' => 60, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed',
            'is_published' => false,
        ]);

        $this->actingAs($admin)->get(route('admin.learning-paths.index', ['term' => $term->id]))
            ->assertOk()->assertSee('المسارات التعليمية الأكاديمية')->assertSee('مسار منظم');

        $this->actingAs($admin)->post(route('admin.learning-paths.units.store', $course), [
            'slug' => 'foundations', 'title_ar' => 'الأساسيات', 'title_en' => 'Foundations',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $unit = $course->units()->where('slug', 'foundations')->firstOrFail();
        $this->assertSame('draft', $unit->status);

        $this->actingAs($admin)->post(route('admin.learning-paths.lessons.store', [$course, $unit]), [
            'slug' => 'first-lesson', 'title' => 'الدرس الأول', 'title_en' => 'First lesson', 'duration_minutes' => 20,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $lesson = $course->lessons()->where('slug', 'first-lesson')->firstOrFail();
        $this->assertSame($unit->id, $lesson->unit_id);
        $this->assertSame('draft', $lesson->status);

        $this->actingAs($admin)->post(route('admin.learning-paths.trainings.store', ['lesson', $lesson->id]), [
            'type' => 'exercise', 'title_ar' => 'تطبيق عملي', 'title_en' => 'Practical exercise', 'passing_score' => 70,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('trainings', ['trainable_type' => $lesson->getMorphClass(), 'trainable_id' => $lesson->id, 'status' => 'draft']);
        $training = Training::where('trainable_type', $lesson->getMorphClass())->where('trainable_id', $lesson->id)->firstOrFail();
        $question = QuestionBankQuestion::create([
            'type' => 'true_false', 'difficulty' => 'easy', 'topic' => 'Basics', 'topic_ar' => 'الأساسيات',
            'prompt' => 'Is this valid?', 'prompt_ar' => 'هل هذا صحيح؟',
            'options' => ['true', 'false'], 'options_ar' => ['صحيح', 'خطأ'], 'correct_option' => 'true',
            'explanation' => 'A test explanation.', 'explanation_ar' => 'شرح اختباري.',
            'created_by' => $admin->id, 'is_active' => true,
        ]);
        $this->actingAs($admin)->post(route('admin.learning-paths.trainings.questions.attach', $training), [
            'question_id' => $question->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('question_bank_question_training', [
            'training_id' => $training->id, 'question_bank_question_id' => $question->id,
        ]);
    }

    public function test_non_content_admin_cannot_manage_learning_paths(): void
    {
        $support = User::factory()->create(['role' => 'admin', 'admin_role' => 'support_agent', 'is_active' => true]);

        $this->actingAs($support)->get(route('admin.learning-paths.index'))->assertForbidden();
    }

    public function test_lesson_training_can_be_created_from_question_bank_quiz(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor', 'is_active' => true]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $course = Course::factory()->create(['is_published' => true, 'price' => 0]);
        $lesson = $course->lessons()->create([
            'slug' => 'quiz-lesson',
            'title' => 'درس الاختبار',
            'title_en' => 'Quiz lesson',
            'duration_minutes' => 20,
            'position' => 1,
            'status' => 'published',
        ]);
        $question = QuestionBankQuestion::create([
            'type' => 'true_false',
            'difficulty' => 'easy',
            'topic' => 'Basics',
            'topic_ar' => 'الأساسيات',
            'prompt' => 'A variable stores a value.',
            'prompt_ar' => 'يخزن المتغير قيمة.',
            'options' => ['True', 'False'],
            'options_ar' => ['صحيح', 'خطأ'],
            'correct_option' => 0,
            'explanation' => 'Correct.',
            'explanation_ar' => 'صحيح.',
            'created_by' => $admin->id,
            'is_active' => true,
        ]);
        $quiz = QuestionBankQuiz::create([
            'name_ar' => 'اختبار درس 1 وحدة 1',
            'name_en' => 'Lesson 1 Unit 1 Quiz',
            'type' => 'required',
            'passing_score' => 80,
            'is_required' => true,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $quiz->questions()->attach($question->id, ['position' => 1]);

        $this->actingAs($admin)->post(route('admin.learning-paths.trainings.store', ['lesson', $lesson->id]), [
            'question_bank_quiz_id' => $quiz->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $training = Training::where('trainable_type', $lesson->getMorphClass())->where('trainable_id', $lesson->id)->firstOrFail();
        $this->assertSame($quiz->id, $training->question_bank_quiz_id);
        $this->assertTrue($training->is_required);
        $this->assertSame(80, $training->passing_score);
        $training->update(['status' => 'published']);

        Sanctum::actingAs($student);
        $this->postJson("/api/courses/{$course->id}/enroll")->assertCreated();
        $this->getJson("/api/courses/{$course->id}/lessons/{$lesson->slug}/practice/{$training->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'اختبار درس 1 وحدة 1')
            ->assertJsonPath('data.questions.0.promptAr', 'يخزن المتغير قيمة.');
    }

    public function test_curriculum_page_shows_courses_only_and_drills_into_units_and_lessons(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor', 'is_active' => true]);
        $term = AcademicTerm::with('academicSubjects.subject')->firstOrFail();
        $course = $this->createAcademicCourse($term, 'drill-down-course', 1);
        $unit = $course->units()->create(['academic_subject_id' => $course->academic_subject_id, 'slug' => 'first-unit', 'title_ar' => 'وحدة الاختبار', 'title_en' => 'Test Unit', 'position' => 1, 'status' => 'draft']);
        $lesson = $course->lessons()->create(['unit_id' => $unit->id, 'slug' => 'hidden-lesson', 'title' => 'درس داخلي', 'title_en' => 'Nested Lesson', 'duration_minutes' => 20, 'position' => 1, 'is_preview' => false, 'status' => 'draft']);

        $this->actingAs($admin)->get(route('admin.learning-paths.index', ['term' => $term->id]))
            ->assertOk()
            ->assertSee($course->title)
            ->assertSee('إدارة الوحدات')
            ->assertDontSee($unit->title_ar)
            ->assertDontSee($lesson->title);

        $this->actingAs($admin)->get(route('admin.learning-paths.courses.show', $course))
            ->assertOk()->assertSee($course->title)->assertSee('معلومات الدورة');

        $this->actingAs($admin)->get(route('admin.learning-paths.courses.units', $course))
            ->assertOk()->assertSee($unit->title_ar)->assertDontSee($lesson->title)
            ->assertSee('add-unit-panel')->assertSee('data-inline-form-toggle="add-unit-panel"', false)
            ->assertSee('pattern="[a-z0-9-]+"', false);

        $this->actingAs($admin)->get(route('admin.learning-paths.units.lessons', [$course, $unit]))
            ->assertOk()->assertSee($lesson->title)
            ->assertSee('add-lesson-panel')->assertSee('data-inline-form-toggle="add-lesson-panel"', false)
            ->assertSee('pattern="[a-z0-9-]+"', false);
    }

    public function test_unit_lessons_page_rejects_a_unit_from_another_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor', 'is_active' => true]);
        $term = AcademicTerm::with('academicSubjects.subject')->firstOrFail();
        $firstCourse = $this->createAcademicCourse($term, 'first-owned-course', 1);
        $secondCourse = $this->createAcademicCourse($term, 'second-owned-course', 2);
        $foreignUnit = $secondCourse->units()->create(['academic_subject_id' => $secondCourse->academic_subject_id, 'slug' => 'foreign-unit', 'title_ar' => 'وحدة أخرى', 'title_en' => 'Foreign Unit', 'position' => 1, 'status' => 'draft']);

        $this->actingAs($admin)->get(route('admin.learning-paths.units.lessons', [
            $firstCourse, $foreignUnit,
        ]))->assertNotFound();
    }

    public function test_content_editor_can_edit_a_unit_without_changing_its_curriculum_relations(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor', 'is_active' => true]);
        $term = AcademicTerm::with('academicSubjects.subject')->firstOrFail();
        $course = $this->createAcademicCourse($term, 'editable-unit-course', 1);
        $unit = $course->units()->create(['academic_subject_id' => $course->academic_subject_id, 'slug' => 'old-unit', 'title_ar' => 'وحدة قديمة', 'title_en' => 'Old Unit', 'position' => 1, 'status' => 'draft']);

        $this->actingAs($admin)->get(route('admin.learning-paths.units.edit', [$course, $unit]))
            ->assertOk()->assertSee('تحرير الوحدة')->assertSee('وحدة قديمة');

        $this->actingAs($admin)->put(route('admin.learning-paths.units.update', [$course, $unit]), [
            'slug' => 'updated-unit', 'title_ar' => 'وحدة محدثة', 'title_en' => 'Updated Unit',
        ])->assertRedirect(route('admin.learning-paths.courses.units', $course))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('units', [
            'id' => $unit->id, 'course_id' => $course->id, 'academic_subject_id' => $course->academic_subject_id,
            'slug' => 'updated-unit', 'title_ar' => 'وحدة محدثة', 'position' => 1, 'status' => 'draft',
        ]);
    }

    public function test_invalid_unit_creation_reopens_the_dialog_with_validation_feedback(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor', 'is_active' => true]);
        $term = AcademicTerm::with('academicSubjects.subject')->firstOrFail();
        $course = $this->createAcademicCourse($term, 'unit-dialog-course', 1);

        $response = $this->actingAs($admin)->from(route('admin.learning-paths.courses.units', $course))
            ->post(route('admin.learning-paths.units.store', $course), [
                'slug' => 'Invalid Slug', 'title_ar' => '', 'title_en' => '',
            ]);

        $response->assertRedirect(route('admin.learning-paths.courses.units', $course))->assertSessionHasErrors(['slug', 'title_ar', 'title_en']);
        $this->actingAs($admin)->get(route('admin.learning-paths.courses.units', $course))
            ->assertOk()->assertSee('aria-expanded="true"', false)->assertSee('Invalid Slug');
    }

    public function test_content_editor_can_save_a_unit_and_keep_the_creation_form_open(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor', 'is_active' => true]);
        $term = AcademicTerm::with('academicSubjects.subject')->firstOrFail();
        $course = $this->createAcademicCourse($term, 'add-another-unit-course', 1);

        $this->actingAs($admin)->post(route('admin.learning-paths.units.store', $course), [
            'slug' => 'saved-unit', 'title_ar' => 'وحدة محفوظة', 'title_en' => 'Saved Unit', 'add_another' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('continue_unit_creation', true);

        $this->assertDatabaseHas('units', ['course_id' => $course->id, 'slug' => 'saved-unit']);
        $this->actingAs($admin)->get(route('admin.learning-paths.courses.units', $course))
            ->assertOk()->assertSee('حفظ وإضافة أخرى')->assertSee('aria-expanded="true"', false);
    }

    public function test_an_empty_unit_cannot_be_published(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor', 'is_active' => true]);
        $term = AcademicTerm::with('academicSubjects.subject')->firstOrFail();
        $course = $this->createAcademicCourse($term, 'empty-unit-publish-course', 1);
        $unit = $course->units()->create(['academic_subject_id' => $course->academic_subject_id, 'slug' => 'empty-unit', 'title_ar' => 'وحدة فارغة', 'title_en' => 'Empty Unit', 'position' => 1, 'status' => 'draft']);

        $this->actingAs($admin)->post(route('admin.learning-paths.state', ['unit', $unit->id]), [
            'status' => 'published',
        ])->assertRedirect()->assertSessionHasErrors('status');

        $this->assertSame('draft', $unit->fresh()->status);

        $course->lessons()->create(['unit_id' => $unit->id, 'slug' => 'publishable-lesson', 'title' => 'درس قابل للنشر', 'title_en' => 'Publishable Lesson', 'duration_minutes' => 10, 'position' => 1, 'is_preview' => false, 'status' => 'draft']);
        $this->actingAs($admin)->post(route('admin.learning-paths.state', ['unit', $unit->id]), [
            'status' => 'published',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('published', $unit->fresh()->status);
    }

    public function test_invalid_lesson_creation_reopens_the_dialog_and_preserves_input(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor', 'is_active' => true]);
        $term = AcademicTerm::with('academicSubjects.subject')->firstOrFail();
        $course = $this->createAcademicCourse($term, 'lesson-dialog-course', 1);
        $unit = $course->units()->create(['academic_subject_id' => $course->academic_subject_id, 'slug' => 'lesson-dialog-unit', 'title_ar' => 'وحدة الدروس', 'title_en' => 'Lessons Unit', 'position' => 1, 'status' => 'draft']);

        $response = $this->actingAs($admin)->from(route('admin.learning-paths.units.lessons', [$course, $unit]))
            ->post(route('admin.learning-paths.lessons.store', [$course, $unit]), [
                'slug' => 'Invalid Lesson', 'title' => '', 'title_en' => '', 'duration_minutes' => 0,
            ]);

        $response->assertRedirect(route('admin.learning-paths.units.lessons', [$course, $unit]))
            ->assertSessionHasErrors(['slug', 'title', 'title_en', 'duration_minutes']);
        $this->actingAs($admin)->get(route('admin.learning-paths.units.lessons', [$course, $unit]))
            ->assertOk()->assertSee('aria-expanded="true"', false)->assertSee('Invalid Lesson');
    }

    public function test_content_editor_can_save_a_lesson_and_keep_the_creation_form_open(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor', 'is_active' => true]);
        $term = AcademicTerm::with('academicSubjects.subject')->firstOrFail();
        $course = $this->createAcademicCourse($term, 'add-another-lesson-course', 1);
        $unit = $course->units()->create(['academic_subject_id' => $course->academic_subject_id, 'slug' => 'lesson-unit', 'title_ar' => 'وحدة الدروس', 'title_en' => 'Lesson Unit', 'position' => 1, 'status' => 'draft']);

        $this->actingAs($admin)->post(route('admin.learning-paths.lessons.store', [$course, $unit]), [
            'slug' => 'saved-lesson', 'title' => 'درس محفوظ', 'title_en' => 'Saved Lesson', 'duration_minutes' => 20, 'add_another' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('continue_lesson_creation', true);

        $this->assertDatabaseHas('lessons', ['course_id' => $course->id, 'unit_id' => $unit->id, 'slug' => 'saved-lesson']);
        $this->actingAs($admin)->get(route('admin.learning-paths.units.lessons', [$course, $unit]))
            ->assertOk()->assertSee('حفظ وإضافة آخر')->assertSee('aria-expanded="true"', false);
    }

    public function test_general_courses_follow_the_same_course_unit_lesson_hierarchy(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor', 'is_active' => true]);
        $course = Course::create([
            'id' => 'general-programming', 'title' => 'برمجة عامة', 'title_en' => 'General Programming',
            'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming',
            'scope' => 'general', 'position' => 1, 'grade' => 'all', 'semester' => 'all',
            'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 0,
            'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => false,
        ]);

        $this->actingAs($admin)->get(route('admin.learning-paths.courses.units', $course))
            ->assertOk()->assertSee('دورة عامة · جميع الطلاب')->assertSee('إضافة وحدة');

        $this->actingAs($admin)->post(route('admin.learning-paths.units.store', $course), [
            'slug' => 'general-foundations', 'title_ar' => 'أساسيات عامة', 'title_en' => 'General Foundations',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('units', [
            'course_id' => $course->id, 'academic_subject_id' => null, 'slug' => 'general-foundations',
        ]);
    }

    private function createAcademicCourse(AcademicTerm $term, string $id, int $position): Course
    {
        $offering = $term->academicSubjects->firstOrFail();

        return Course::create([
            'id' => $id, 'title' => 'دورة '.$position, 'title_en' => 'Course '.$position,
            'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => $offering->subject->name_en,
            'scope' => 'academic', 'academic_subject_id' => $offering->id, 'position' => $position,
            'grade' => $term->grade->code, 'semester' => $term->code, 'level' => 'Beginner',
            'duration_minutes' => 60, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed',
            'is_published' => false,
        ]);
    }

    public function test_general_learning_tab_is_a_real_page_mode(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.learning-paths.index', ['mode' => 'general']))
            ->assertOk()
            ->assertSee('دورات التعلّم العام')
            ->assertSee('mode=general', false)
            ->assertDontSee('شجرة المنهج');
    }

    public function test_grade_change_ignores_a_stale_term_from_another_grade(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor', 'is_active' => true]);
        $firstGradeTerm = AcademicTerm::whereHas('grade', fn ($query) => $query->where('code', 'grade-1'))->firstOrFail();
        $secondGradeTerm = AcademicTerm::whereHas('grade', fn ($query) => $query->where('code', 'grade-2'))->with('academicSubjects.subject')->firstOrFail();
        $offering = $secondGradeTerm->academicSubjects->firstOrFail();
        Course::create([
            'id' => 'second-grade-only', 'title' => 'دورة الصف الثاني', 'title_en' => 'Second grade course',
            'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => $offering->subject->name_en,
            'scope' => 'academic', 'academic_subject_id' => $offering->id, 'position' => 1,
            'grade' => $secondGradeTerm->grade->code, 'semester' => $secondGradeTerm->code,
            'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 0,
            'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.learning-paths.index', [
            'grade' => $secondGradeTerm->academic_grade_id,
            'term' => $firstGradeTerm->id,
        ]))->assertOk()->assertSee('دورة الصف الثاني');
    }

    public function test_training_attempts_remain_separate_from_curriculum_definition(): void
    {
        $this->assertNotSame('training_attempts', (new Training)->getTable());
    }
}
