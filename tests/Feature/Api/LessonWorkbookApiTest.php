<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonSection;
use App\Models\QuestionBankQuestion;
use App\Models\Training;
use App\Models\Unit;
use App\Models\User;
use App\Services\LessonWorkbookService;
use App\Services\UnitWorkbookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonWorkbookApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workbook_print_uses_structured_section_partials_and_visibility(): void
    {
        [, $lesson] = $this->enrolledLesson();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $lesson->sections()->create([
            'type' => 'comparison',
            'title' => 'مقارنة المفاهيم',
            'content' => [
                'text' => 'قارن بين المفهومين.',
                'items' => ['الميزة | المتغير | الثابت', 'القيمة | قابلة للتغيير | لا تتغير'],
            ],
            'settings' => LessonSection::DEFAULT_SETTINGS,
            'sort_order' => 1,
        ]);
        $lesson->sections()->create([
            'type' => 'common_mistake',
            'title' => 'خطأ شائع',
            'content' => [
                'text' => '',
                'items' => ['استخدام اسم غير واضح => اختر اسمًا يصف القيمة'],
            ],
            'settings' => LessonSection::DEFAULT_SETTINGS,
            'sort_order' => 2,
        ]);
        $lesson->sections()->create([
            'type' => 'answer_key',
            'title' => 'إجابات مخفية عن الكراسة',
            'content' => ['text' => 'لا يجب أن تظهر', 'items' => []],
            'settings' => array_merge(LessonSection::DEFAULT_SETTINGS, ['show_in_workbook' => false]),
            'sort_order' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.lessons.workbook-preview', [$lesson->course, $lesson]))
            ->assertOk()
            ->assertSee('مقارنة المفاهيم')
            ->assertSee('المتغير')
            ->assertSee('اختر اسمًا يصف القيمة')
            ->assertDontSee('لا يجب أن تظهر');
    }

    public function test_workbook_print_renders_structured_tables_and_images(): void
    {
        [, $lesson] = $this->enrolledLesson();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $lesson->sections()->create([
            'type' => 'table',
            'title' => 'جدول المقارنة',
            'content' => [
                'text' => '',
                'items' => [],
                'table' => [
                    'rows' => [
                        ['المعيار', 'HTML', 'CSS'],
                        ['الدور', 'بناء المحتوى', 'تنسيق العرض'],
                    ],
                ],
            ],
            'settings' => LessonSection::DEFAULT_SETTINGS,
            'sort_order' => 1,
        ]);
        $lesson->sections()->create([
            'type' => 'image',
            'title' => 'مخطط الدرس',
            'content' => [
                'text' => '',
                'items' => [],
                'image' => [
                    'url' => 'https://example.test/lesson-diagram.png',
                    'alt' => 'مخطط يوضح العلاقة بين HTML و CSS',
                    'caption' => 'مخطط مبسط للدرس',
                    'alignment' => 'center',
                    'print_width' => 'large',
                ],
            ],
            'settings' => LessonSection::DEFAULT_SETTINGS,
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.lessons.workbook-preview', [$lesson->course, $lesson]))
            ->assertOk()
            ->assertSee('جدول المقارنة')
            ->assertSee('بناء المحتوى')
            ->assertSee('https://example.test/lesson-diagram.png')
            ->assertSee('مخطط مبسط للدرس');
    }

    public function test_workbook_print_renders_structured_questions(): void
    {
        [, $lesson] = $this->enrolledLesson();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $lesson->sections()->create([
            'type' => 'question',
            'title' => 'تحقق سريع',
            'content' => [
                'text' => '',
                'items' => [],
                'question' => [
                    'type' => 'multiple_choice',
                    'prompt' => 'أي لغة تُستخدم لبناء هيكل الصفحة؟',
                    'options' => ['HTML', 'CSS', 'SQL'],
                    'answer' => 'HTML',
                    'explanation' => 'HTML تصف بنية المحتوى.',
                    'answer_space' => 'small',
                ],
            ],
            'settings' => LessonSection::DEFAULT_SETTINGS,
            'sort_order' => 1,
        ]);
        $lesson->sections()->create([
            'type' => 'question_group',
            'title' => 'أسئلة قصيرة',
            'content' => [
                'text' => '',
                'items' => [],
                'question_group' => [
                    'questions' => [
                        ['prompt' => 'عرّف الوسم.'],
                        ['prompt' => 'اذكر وظيفة CSS.'],
                    ],
                    'answer_space' => 'large',
                ],
            ],
            'settings' => LessonSection::DEFAULT_SETTINGS,
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.lessons.workbook-preview', [$lesson->course, $lesson]))
            ->assertOk()
            ->assertSee('أي لغة تُستخدم لبناء هيكل الصفحة؟')
            ->assertSee('HTML')
            ->assertDontSee('HTML تصف بنية المحتوى.')
            ->assertSee('عرّف الوسم.')
            ->assertSee('اذكر وظيفة CSS.');

        $lesson->update(['include_workbook_answer_key' => true]);

        $this->actingAs($admin)
            ->get(route('admin.lessons.workbook-preview', [$lesson->course, $lesson]))
            ->assertOk()
            ->assertSee('HTML تصف بنية المحتوى.');
    }

    public function test_workbook_print_respects_cover_info_toc_and_answer_key_settings(): void
    {
        [, $lesson] = $this->enrolledLesson();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $lesson->update([
            'include_workbook_cover' => false,
            'include_workbook_info' => false,
            'include_workbook_toc' => false,
            'include_workbook_answer_key' => false,
        ]);
        $lesson->sections()->create([
            'type' => 'answer_key',
            'title' => 'إجابات الدرس',
            'content' => ['text' => 'الإجابة مخفية افتراضيًا', 'items' => []],
            'settings' => LessonSection::DEFAULT_SETTINGS,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.lessons.workbook-preview', [$lesson->course, $lesson]))
            ->assertOk()
            ->assertDontSee('كراسة درس')
            ->assertDontSee('فكرة الدرس')
            ->assertDontSee('أهداف التعلم')
            ->assertDontSee('خريطة الدرس')
            ->assertDontSee('الإجابة مخفية افتراضيًا');

        $lesson->update(['include_workbook_answer_key' => true]);

        $this->actingAs($admin)
            ->get(route('admin.lessons.workbook-preview', [$lesson->course, $lesson]))
            ->assertOk()
            ->assertSee('الإجابة مخفية افتراضيًا');
    }

    public function test_admin_can_preview_workbook_for_draft_lesson(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $course = Course::factory()->create(['is_published' => false]);
        $lesson = Lesson::factory()->for($course)->create([
            'status' => 'draft',
            'objectives' => ['مراجعة محتوى المسودة'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.lessons.workbook-preview', [$course, $lesson]))
            ->assertOk()
            ->assertSee('مراجعة محتوى المسودة');
    }

    public function test_admin_can_preview_teacher_edition_with_teacher_only_sections_and_answers(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $course = Course::factory()->create(['is_published' => false]);
        $lesson = Lesson::factory()->for($course)->create(['status' => 'draft']);
        $lesson->sections()->create([
            'type' => 'important_note',
            'title' => 'ملاحظة للمعلم',
            'content' => ['text' => 'اطلب من الطلاب مقارنة الحلول.', 'items' => []],
            'settings' => array_merge(LessonSection::DEFAULT_SETTINGS, [
                'show_in_workbook' => false,
                'show_in_teacher_edition' => true,
            ]),
            'sort_order' => 1,
        ]);
        $lesson->sections()->create([
            'type' => 'question',
            'title' => 'سؤال مع إجابة',
            'content' => [
                'question' => [
                    'type' => 'short_answer',
                    'prompt' => 'ما وظيفة المتغير؟',
                    'options' => [],
                    'answer' => 'تخزين قيمة.',
                    'explanation' => 'يمكن استخدامها لاحقًا.',
                    'answer_space' => 'medium',
                ],
            ],
            'settings' => LessonSection::DEFAULT_SETTINGS,
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.lessons.teacher-edition-preview', [$course, $lesson]))
            ->assertOk()
            ->assertSee('نسخة المعلم')
            ->assertSee('اطلب من الطلاب مقارنة الحلول.')
            ->assertSee('يمكن استخدامها لاحقًا.');
    }

    public function test_admin_can_download_teacher_edition_pdf(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $course = Course::factory()->create(['is_published' => false]);
        $lesson = Lesson::factory()->for($course)->create(['status' => 'draft']);
        $pdfPath = tempnam(sys_get_temp_dir(), 'teacher-edition-test-');
        file_put_contents($pdfPath, '%PDF-1.4 teacher');

        $this->mock(LessonWorkbookService::class, function ($mock) use ($lesson, $pdfPath): void {
            $mock->shouldReceive('generatePdf')
                ->once()
                ->withArgs(fn (Lesson $givenLesson, string $edition) => $givenLesson->is($lesson) && $edition === 'teacher')
                ->andReturn(['path' => $pdfPath, 'filename' => 'lesson-teacher-edition-test.pdf']);
        });

        $this->actingAs($admin)
            ->get(route('admin.lessons.teacher-edition-download', [$course, $lesson]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_can_preview_unit_workbook_with_published_lessons(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$course, $unit] = $this->unitWithLessons();

        $this->actingAs($admin)
            ->get(route('admin.learning-paths.units.workbook-preview', [$course, $unit]))
            ->assertOk()
            ->assertSee('كراسة وحدة')
            ->assertSee('درس منشور')
            ->assertSee('شرح منشور')
            ->assertDontSee('درس مسودة');
    }

    public function test_admin_can_preview_unit_teacher_edition_with_drafts_teacher_sections_and_answers(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$course, $unit] = $this->unitWithLessons();

        $this->actingAs($admin)
            ->get(route('admin.learning-paths.units.teacher-edition-preview', [$course, $unit]))
            ->assertOk()
            ->assertSee('نسخة المعلم')
            ->assertSee('درس منشور')
            ->assertSee('درس مسودة')
            ->assertSee('ملاحظة خاصة بالمعلم')
            ->assertSee('الإجابة تظهر للمعلم.');
    }

    public function test_admin_can_download_unit_teacher_edition_pdf(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$course, $unit] = $this->unitWithLessons();
        $pdfPath = tempnam(sys_get_temp_dir(), 'unit-teacher-edition-test-');
        file_put_contents($pdfPath, '%PDF-1.4 unit teacher');

        $this->mock(UnitWorkbookService::class, function ($mock) use ($unit, $pdfPath): void {
            $mock->shouldReceive('generatePdf')
                ->once()
                ->withArgs(fn (Unit $givenUnit, string $edition) => $givenUnit->is($unit) && $edition === 'teacher')
                ->andReturn(['path' => $pdfPath, 'filename' => 'unit-teacher-edition-test.pdf']);
        });

        $this->actingAs($admin)
            ->get(route('admin.learning-paths.units.teacher-edition-download', [$course, $unit]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_can_manage_structured_lesson_sections(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $course = Course::factory()->create(['is_published' => false]);
        $lesson = Lesson::factory()->for($course)->create(['status' => 'draft']);

        $this->actingAs($admin)->post(route('admin.lesson-sections.store', [$course, $lesson]), [
            'type' => 'introduction',
            'title' => 'مقدمة',
            'content_text' => 'نبدأ الدرس بسؤال قصير.',
            'items_text' => "الفكرة الأولى\nالفكرة الثانية",
            'mistake' => 'استخدام اسم غير واضح',
            'correction' => 'اختر اسمًا يصف القيمة',
            'worked_problem' => 'احسب قيمة المتغير بعد التحديث.',
            'worked_solution' => 'القيمة النهائية هي 5.',
            'code_body' => "let total = 5;\nconsole.log(total);",
            'code_expected_output' => '5',
            'table_text' => "المعيار | HTML | CSS\nالدور | بناء المحتوى | تنسيق العرض",
            'image_url' => 'https://example.test/diagram.png',
            'image_alt' => 'مخطط الدرس',
            'image_caption' => 'تعليق مناسب للطباعة',
            'image_alignment' => 'center',
            'image_print_width' => 'large',
            'question_type' => 'multiple_choice',
            'question_prompt' => 'أي لغة تبني هيكل الصفحة؟',
            'question_options_text' => "HTML\nCSS\nJavaScript",
            'question_answer' => 'HTML',
            'question_explanation' => 'لأنها تحدد عناصر الصفحة.',
            'question_answer_space' => 'small',
            'question_group_text' => "عرّف المتغير.\nاذكر مثالًا.",
            'question_group_answer_space' => 'large',
            'settings' => ['show_on_web' => '1', 'show_in_workbook' => '1', 'avoid_page_break_inside' => '1'],
        ])->assertRedirect();

        $section = LessonSection::firstOrFail();
        $this->assertSame(['الفكرة الأولى', 'الفكرة الثانية'], $section->content['items']);
        $this->assertTrue($section->settings['show_on_web']);
        $this->assertSame('استخدام اسم غير واضح', $section->content['common_mistake']['mistake']);
        $this->assertSame('القيمة النهائية هي 5.', $section->content['worked_example']['solution']);
        $this->assertSame('5', $section->content['code_example']['expected_output']);
        $this->assertSame(['المعيار', 'HTML', 'CSS'], $section->content['table']['rows'][0]);
        $this->assertSame('تعليق مناسب للطباعة', $section->content['image']['caption']);
        $this->assertSame('multiple_choice', $section->content['question']['type']);
        $this->assertSame(['HTML', 'CSS', 'JavaScript'], $section->content['question']['options']);
        $this->assertSame('اذكر مثالًا.', $section->content['question_group']['questions'][1]['prompt']);

        $this->put(route('admin.lesson-sections.update', [$course, $lesson, $section]), [
            'type' => 'important_note',
            'title' => 'انتبه',
            'content_text' => 'هذه نقطة مهمة.',
            'settings' => ['show_on_web' => '1', 'show_in_workbook' => '0', 'avoid_page_break_inside' => '1'],
        ])->assertRedirect();
        $this->assertDatabaseHas('lesson_sections', ['id' => $section->id, 'type' => 'important_note', 'title' => 'انتبه']);

        $this->post(route('admin.lesson-sections.duplicate', [$course, $lesson, $section->fresh()]))->assertRedirect();
        $this->assertSame(2, $lesson->sections()->count());

        $copy = $lesson->sections()->orderByDesc('sort_order')->firstOrFail();
        $this->post(route('admin.lesson-sections.move', [$course, $lesson, $copy, 'up']))->assertRedirect();
        $this->assertSame(1, (int) $copy->fresh()->sort_order);

        $this->delete(route('admin.lesson-sections.destroy', [$course, $lesson, $copy]))->assertRedirect();
        $this->assertDatabaseMissing('lesson_sections', ['id' => $copy->id]);
    }

    public function test_admin_cannot_attach_image_section_to_media_from_another_lesson(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $course = Course::factory()->create(['is_published' => false]);
        $lesson = Lesson::factory()->for($course)->create(['status' => 'draft']);
        $otherLesson = Lesson::factory()->for($course)->create(['status' => 'draft', 'position' => 2]);
        $asset = $otherLesson->mediaAssets()->create([
            'kind' => 'resource',
            'disk' => 'local',
            'path' => 'lessons/other/diagram.png',
            'original_name' => 'diagram.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
        ]);

        $this->actingAs($admin)->post(route('admin.lesson-sections.store', [$course, $lesson]), [
            'type' => 'image',
            'title' => 'صورة من درس آخر',
            'image_media_asset_id' => $asset->id,
            'settings' => ['show_on_web' => '1', 'show_in_workbook' => '1', 'avoid_page_break_inside' => '1'],
        ])->assertStatus(422);
    }

    public function test_student_course_payload_only_includes_web_visible_lesson_sections(): void
    {
        [$course, $lesson, $student] = $this->enrolledLesson();
        $lesson->sections()->create([
            'type' => 'explanation',
            'title' => 'شرح للطالب',
            'content' => ['text' => 'محتوى ظاهر للطالب', 'items' => []],
            'settings' => LessonSection::DEFAULT_SETTINGS,
            'sort_order' => 1,
        ]);
        $lesson->sections()->create([
            'type' => 'answer_key',
            'title' => 'إجابة مخفية',
            'content' => ['text' => 'لا تظهر في صفحة الطالب', 'items' => []],
            'settings' => array_merge(LessonSection::DEFAULT_SETTINGS, ['show_on_web' => false]),
            'sort_order' => 2,
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/courses/'.$course->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.lessons.0.sections')
            ->assertJsonPath('data.lessons.0.sections.0.title', 'شرح للطالب');
    }

    public function test_student_course_payload_includes_structured_table_and_image_content(): void
    {
        [$course, $lesson, $student] = $this->enrolledLesson();
        $lesson->sections()->create([
            'type' => 'image',
            'title' => 'مخطط مرئي',
            'content' => [
                'text' => '',
                'items' => [],
                'table' => ['rows' => [['المعيار', 'القيمة']]],
                'image' => [
                    'url' => 'https://example.test/student-diagram.png',
                    'alt' => 'مخطط مرئي',
                    'caption' => 'تعليق يظهر للطالب',
                    'alignment' => 'center',
                    'print_width' => 'medium',
                ],
            ],
            'settings' => LessonSection::DEFAULT_SETTINGS,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/courses/'.$course->id)
            ->assertOk()
            ->assertJsonPath('data.lessons.0.sections.0.content.table.rows.0.0', 'المعيار')
            ->assertJsonPath('data.lessons.0.sections.0.content.image.caption', 'تعليق يظهر للطالب');
    }

    public function test_student_course_payload_includes_structured_question_content(): void
    {
        [$course, $lesson, $student] = $this->enrolledLesson();
        $lesson->sections()->create([
            'type' => 'question',
            'title' => 'سؤال منظم',
            'content' => [
                'text' => '',
                'items' => [],
                'question' => [
                    'type' => 'short_answer',
                    'prompt' => 'ما وظيفة المتغير؟',
                    'options' => [],
                    'answer' => 'تخزين قيمة.',
                    'explanation' => 'يمكن استخدام القيمة لاحقًا.',
                    'answer_space' => 'medium',
                ],
            ],
            'settings' => LessonSection::DEFAULT_SETTINGS,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/courses/'.$course->id)
            ->assertOk()
            ->assertJsonPath('data.lessons.0.sections.0.content.question.prompt', 'ما وظيفة المتغير؟')
            ->assertJsonPath('data.lessons.0.sections.0.content.question.answer_space', 'medium');
    }

    private function enrolledLesson(): array
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->for($course)->create([
            'slug' => 'variables',
            'title' => 'المتغيرات',
            'title_en' => 'Variables',
            'status' => 'published',
            'summary' => 'ملخص واضح للدرس.',
            'objectives' => ['أن يشرح الطالب المتغيرات'],
            'key_points' => ['المتغير يخزن قيمة قابلة للتغيير'],
        ]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);

        return [$course, $lesson, $student];
    }

    private function unitWithLessons(): array
    {
        $course = Course::factory()->create(['is_published' => false]);
        $unit = Unit::create([
            'course_id' => $course->id,
            'academic_subject_id' => $course->academic_subject_id,
            'slug' => 'unit-one',
            'title_ar' => 'الوحدة الأولى',
            'title_en' => 'Unit One',
            'position' => 1,
            'status' => 'draft',
        ]);
        $published = Lesson::factory()->for($course)->create([
            'unit_id' => $unit->id,
            'title' => 'درس منشور',
            'title_en' => 'Published Lesson',
            'status' => 'published',
            'position' => 1,
            'summary' => 'ملخص الدرس المنشور.',
        ]);
        $draft = Lesson::factory()->for($course)->create([
            'unit_id' => $unit->id,
            'title' => 'درس مسودة',
            'title_en' => 'Draft Lesson',
            'status' => 'draft',
            'position' => 2,
        ]);
        $published->sections()->create([
            'type' => 'explanation',
            'title' => 'شرح منشور',
            'content' => ['text' => 'شرح منشور داخل كراسة الوحدة.', 'items' => []],
            'settings' => LessonSection::DEFAULT_SETTINGS,
            'sort_order' => 1,
        ]);
        $published->sections()->create([
            'type' => 'important_note',
            'title' => 'تعليمات المعلم',
            'content' => ['text' => 'ملاحظة خاصة بالمعلم', 'items' => []],
            'settings' => array_merge(LessonSection::DEFAULT_SETTINGS, [
                'show_in_workbook' => false,
                'show_in_teacher_edition' => true,
            ]),
            'sort_order' => 2,
        ]);
        $published->sections()->create([
            'type' => 'question',
            'title' => 'سؤال',
            'content' => [
                'question' => [
                    'type' => 'short_answer',
                    'prompt' => 'سؤال للوحدة؟',
                    'options' => [],
                    'answer' => 'الإجابة تظهر للمعلم.',
                    'explanation' => '',
                    'answer_space' => 'small',
                ],
            ],
            'settings' => LessonSection::DEFAULT_SETTINGS,
            'sort_order' => 3,
        ]);
        $draft->sections()->create([
            'type' => 'explanation',
            'title' => 'شرح المسودة',
            'content' => ['text' => 'هذا يظهر في نسخة المعلم فقط.', 'items' => []],
            'settings' => LessonSection::DEFAULT_SETTINGS,
            'sort_order' => 1,
        ]);

        return [$course, $unit];
    }

    private function attachPublishedPractice(Lesson $lesson): void
    {
        $question = QuestionBankQuestion::create([
            'type' => 'short_answer',
            'difficulty' => 'easy',
            'topic' => 'Variables',
            'topic_ar' => 'المتغيرات',
            'prompt' => 'What does a variable do?',
            'prompt_ar' => 'ما وظيفة المتغير؟',
            'options' => [],
            'options_ar' => [],
            'correct_option' => 0,
            'explanation' => 'A variable stores a value.',
            'explanation_ar' => 'يخزن المتغير قيمة.',
            'is_active' => true,
        ]);

        $training = Training::create([
            'trainable_type' => Lesson::class,
            'trainable_id' => $lesson->id,
            'type' => 'practice',
            'title_ar' => 'تدريب الدرس',
            'title_en' => 'Lesson Practice',
            'position' => 1,
            'status' => 'published',
            'is_required' => true,
            'passing_score' => 70,
        ]);

        $training->questions()->attach($question->id, ['position' => 1]);
    }
}
