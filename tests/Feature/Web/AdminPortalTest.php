<?php

namespace Tests\Feature\Web;

use App\Http\Resources\LessonResource;
use App\Models\Announcement;
use App\Models\AssessmentAttempt;
use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\Cohort;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\InstructorProfile;
use App\Models\LessonCompletion;
use App\Models\LoginEvent;
use App\Models\ManualPaymentSubmission;
use App\Models\MediaAsset;
use App\Models\MediaLibraryItem;
use App\Models\Order;
use App\Models\QuestionBankQuestion;
use App\Models\QuestionBankQuiz;
use App\Models\ScheduledCourseAction;
use App\Models\StudentSupportCase;
use App\Models\SubscriptionPlan;
use App\Models\Training;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_portal_uses_session_authentication_and_role_protection(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['role' => 'student']))->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin')->assertOk()->assertSee('نظرة عامة');
    }

    public function test_admin_header_shows_and_marks_actionable_notifications_as_read(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $notification = UserNotification::create([
            'user_id' => $admin->id,
            'type' => 'student_registration',
            'title' => 'Registration pending',
            'title_ar' => 'تسجيل طالب جديد بانتظار المراجعة',
            'message' => 'A student submitted payment evidence.',
            'message_ar' => 'أرسل طالب جديد إثبات الدفع.',
            'action_url' => '/admin/payment-reviews/42',
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('تسجيل طالب جديد بانتظار المراجعة')
            ->assertSee('1 تحتاج انتباهك');

        $this->post(route('admin.notifications.read', $notification))
            ->assertRedirect('/admin/payment-reviews/42');

        $this->assertNotNull($notification->refresh()->read_at);

        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($otherAdmin)->post(route('admin.notifications.read', $notification))->assertNotFound();
    }

    public function test_admin_can_manage_course_content_through_blade_forms(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/courses', $this->coursePayload())->assertRedirect();
        $course = Course::findOrFail('blade-course');
        $this->post('/admin/courses/'.$course->id.'/lessons', ['slug' => 'intro', 'title' => 'مقدمة', 'title_en' => 'Introduction', 'duration_minutes' => 20, 'position' => 1, 'is_preview' => 1])->assertRedirect();
        $this->post('/admin/courses/'.$course->id.'/assessment', ['duration_minutes' => 10, 'passing_score' => 70])->assertRedirect();
        $this->post('/admin/courses/'.$course->id.'/questions', ['topic' => 'Basics', 'topic_ar' => 'أساسيات', 'prompt' => 'Correct?', 'prompt_ar' => 'صحيح؟', 'options_text' => "Yes\nNo", 'options_ar_text' => "نعم\nلا", 'correct_option' => 0, 'explanation' => 'Yes.', 'explanation_ar' => 'نعم.', 'position' => 1])->assertRedirect();

        $this->get('/admin/courses/'.$course->id.'/edit')->assertOk()->assertSee('مقدمة')->assertSee('صحيح؟');
        $this->assertDatabaseHas('audit_logs', ['action' => 'lesson.created']);
        $this->assertDatabaseHas('assessment_questions', ['assessment_id' => $course->assessment->id]);
    }

    public function test_admin_login_rejects_non_admin_and_accepts_admin(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('data-password-toggle="password"', false)
            ->assertSee('إظهار كلمة المرور');

        $student = User::factory()->create(['role' => 'student', 'password' => 'password']);
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'password']);
        $this->post('/admin/login', ['email' => $student->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_view_a_student_learning_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Student Profile']);
        $course = Course::factory()->create(['title' => 'مسار الطالب']);
        $lesson = $course->lessons()->create(['slug' => 'profile-lesson', 'title' => 'درس مكتمل', 'title_en' => 'Completed lesson', 'duration_minutes' => 20, 'position' => 1]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
        LessonCompletion::create(['user_id' => $student->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertSee('Student Profile')
            ->assertSee('مسار الطالب')
            ->assertSee('100%')
            ->assertSee('درس مكتمل');
    }

    public function test_admin_can_view_student_profile_with_subscription_order_without_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Promo Student']);
        $plan = SubscriptionPlan::query()->create([
            'code' => 'term-plan',
            'name_ar' => 'اشتراك فصل دراسي',
            'name_en' => 'Term subscription',
            'billing_period' => 'semester',
            'price' => 0,
            'currency' => 'EGP',
            'is_active' => true,
        ]);

        $student->orders()->create([
            'public_id' => 'ORDER-PROMO-PROFILE',
            'beneficiary_user_id' => $student->id,
            'product_type' => 'subscription',
            'subscription_plan_id' => $plan->id,
            'original_amount' => 750,
            'discount_amount' => 750,
            'amount' => 0,
            'currency' => 'EGP',
            'status' => 'completed',
            'assignment_status' => 'assigned',
            'payment_provider' => 'promo_code',
            'payment_method' => 'promo_code',
            'session_token_hash' => hash('sha256', 'promo-profile-order'),
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertSee('Promo Student')
            ->assertSee('اشتراك فصل دراسي')
            ->assertSee('promo_code');
    }

    public function test_admin_can_update_unused_coupon_and_delete_only_without_redemptions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plan = SubscriptionPlan::query()->create([
            'code' => 'promo-plan',
            'name_ar' => 'خطة ترويجية',
            'name_en' => 'Promo plan',
            'billing_period' => 'semester',
            'price' => 750,
            'currency' => 'EGP',
            'is_active' => true,
        ]);
        $coupon = Coupon::create([
            'code' => 'OLDPROMO',
            'type' => 'percent',
            'value' => 10,
            'minimum_amount' => 0,
            'max_per_user' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.commerce.coupons.update', $coupon), [
            'code' => 'NEWPROMO',
            'type' => 'percent',
            'value' => 25,
            'minimum_amount' => 0,
            'max_per_user' => 1,
            'max_redemptions' => 10,
            'is_active' => 1,
            'subscription_plan_ids' => [$plan->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'code' => 'NEWPROMO', 'value' => 25]);
        $this->assertDatabaseHas('coupon_subscription_plan', ['coupon_id' => $coupon->id, 'subscription_plan_id' => $plan->id]);

        $student = User::factory()->create(['role' => 'student']);
        $order = $student->orders()->create([
            'public_id' => 'ORDER-COUPON-USED',
            'beneficiary_user_id' => $student->id,
            'product_type' => 'subscription',
            'subscription_plan_id' => $plan->id,
            'original_amount' => 750,
            'discount_amount' => 187.5,
            'amount' => 562.5,
            'currency' => 'EGP',
            'status' => 'submitted',
            'assignment_status' => 'pending',
            'payment_provider' => 'manual_transfer',
            'payment_method' => 'instapay',
            'session_token_hash' => hash('sha256', 'used-coupon-order'),
        ]);
        $coupon->redemptions()->create(['user_id' => $student->id, 'order_id' => $order->id, 'discount_amount' => 187.5, 'status' => 'reserved']);

        $this->delete(route('admin.commerce.coupons.destroy', $coupon))
            ->assertRedirect()
            ->assertSessionHasErrors('coupon');
        $unused = Coupon::create(['code' => 'UNUSED', 'type' => 'fixed', 'value' => 50, 'minimum_amount' => 0, 'max_per_user' => 1, 'is_active' => true]);
        $this->delete(route('admin.commerce.coupons.destroy', $unused))->assertRedirect();
        $this->assertDatabaseMissing('coupons', ['id' => $unused->id]);
    }

    public function test_student_profile_is_admin_only_and_rejects_non_student_records(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $parent = User::factory()->create(['role' => 'parent']);

        $this->actingAs($student)->get(route('admin.users.show', $student))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.users.show', $parent))->assertNotFound();
    }

    public function test_admin_can_edit_and_reorder_lessons_and_remove_media(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['is_published' => false]);
        $first = $course->lessons()->create(['slug' => 'first', 'title' => 'الأول', 'title_en' => 'First', 'duration_minutes' => 10, 'position' => 1]);
        $second = $course->lessons()->create(['slug' => 'second', 'title' => 'الثاني', 'title_en' => 'Second', 'duration_minutes' => 10, 'position' => 2]);
        Storage::disk('local')->put('course-media/test.pdf', 'content');
        $media = MediaAsset::create(['lesson_id' => $first->id, 'kind' => 'resource', 'disk' => 'local', 'path' => 'course-media/test.pdf', 'original_name' => 'test.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 7]);

        $this->actingAs($admin)->get(route('admin.lessons.edit', [$course, $first]))
            ->assertOk()
            ->assertSee('حفظ بيانات الدرس')
            ->assertSee('قارئ PDF')
            ->assertDontSee('حفظ إعدادات PDF');
        $this->get(route('admin.lessons.edit', [$course, $first]).'?tab=pdf')->assertOk()->assertSee('رفع وربط PDF')->assertSee(route('admin.lesson-pdf.store', [$course, $first]), false)->assertSee('data-pdf-upload-form', false)->assertSee('data-pdf-upload-status', false)->assertSee('حفظ إعدادات PDF')->assertDontSee('حفظ بيانات الدرس');
        $this->get(route('admin.lessons.edit', [$course, $first]).'?tab=workbook')->assertOk()->assertSee('حفظ إعدادات الكراسة')->assertDontSee('حفظ بيانات الدرس');
        $this->get(route('admin.lessons.edit', [$course, $first]).'?tab=learning')->assertOk()->assertSee('حفظ أهداف التعلم')->assertDontSee('حفظ بيانات الدرس');
        $this->get(route('admin.lessons.edit', [$course, $first]).'?tab=completion')->assertOk()->assertSee('حفظ الإكمال والنصوص')->assertDontSee('حفظ بيانات الدرس');
        $this->get(route('admin.lessons.edit', [$course, $first]).'?tab=media')->assertOk()->assertSee('الوسائط الخاصة')->assertDontSee('تدريب الدرس')->assertDontSee('حفظ بيانات الدرس');
        $this->get(route('admin.lessons.edit', [$course, $first]).'?tab=questions')->assertOk()->assertSee('تدريب الدرس')->assertDontSee('الوسائط الخاصة')->assertDontSee('حفظ بيانات الدرس');
        $this->get(route('admin.lessons.edit', [$course, $first]).'?tab=content')->assertOk()->assertSee('محتوى الدرس المنظم')->assertDontSee('حفظ بيانات الدرس');
        $this->withSession(['error' => 'رسالة خطأ ظاهرة'])->get(route('admin.lessons.edit', [$course, $first]).'?tab=pdf')
            ->assertOk()
            ->assertSee('تعذر تنفيذ العملية')
            ->assertSee('رسالة خطأ ظاهرة');

        $this->actingAs($admin)->put(route('admin.lessons.update', [$course, $first]), [
            'slug' => 'first', 'title' => 'الدرس الأول', 'title_en' => 'First lesson',
            'duration_minutes' => 25, 'position' => 1, 'is_preview' => 1,
            'transcript_text' => "السطر الأول\nالسطر الثاني", 'transcript_en_text' => "First line\nSecond line",
        ])->assertRedirect();
        $this->post(route('admin.lessons.move', [$course, $second, 'up']))->assertRedirect();
        $this->delete(route('admin.media.destroy', [$course, $first, $media]))->assertRedirect();

        $this->assertDatabaseHas('lessons', ['id' => $first->id, 'title' => 'الدرس الأول', 'duration_minutes' => 25, 'position' => 2, 'is_preview' => true]);
        $this->assertSame(['السطر الأول', 'السطر الثاني'], $first->fresh()->transcript);
        $this->assertDatabaseHas('lessons', ['id' => $second->id, 'position' => 1]);
        $this->assertDatabaseMissing('media_assets', ['id' => $media->id]);
        Storage::disk('local')->assertExists('course-media/test.pdf');
    }

    public function test_admin_lesson_update_cleans_malformed_transcript_text_before_json_save(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['is_published' => false]);
        $lesson = $course->lessons()->create([
            'slug' => 'malformed-transcript',
            'title' => 'درس',
            'title_en' => 'Lesson',
            'duration_minutes' => 10,
            'position' => 1,
        ]);

        $this->actingAs($admin)->put(route('admin.lessons.update', [$course, $lesson]), [
            'transcript_text' => "valid line\nbad byte \xC3\x28",
        ])->assertRedirect();

        $this->assertSame(['valid line', 'bad byte ('], $lesson->fresh()->transcript);
    }

    public function test_admin_lesson_update_cleans_malformed_direct_transcript_array_before_json_save(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['is_published' => false]);
        $lesson = $course->lessons()->create([
            'slug' => 'malformed-transcript-array',
            'title' => 'درس',
            'title_en' => 'Lesson',
            'duration_minutes' => 10,
            'position' => 1,
        ]);

        $this->actingAs($admin)->put(route('admin.lessons.update', [$course, $lesson]), [
            'transcript' => ['valid line', "bad byte \xC3\x28"],
        ])->assertRedirect();

        $this->assertSame(['valid line', 'bad byte ('], $lesson->fresh()->transcript);
    }

    public function test_admin_can_upload_lesson_pdf_from_pdf_tab(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['is_published' => false]);
        $lesson = $course->lessons()->create(['slug' => 'pdf-upload', 'title' => 'درس PDF', 'title_en' => 'PDF lesson', 'duration_minutes' => 10, 'position' => 1]);

        $this->actingAs($admin)->post(route('admin.lesson-pdf.store', [$course, $lesson]), [
            'file' => UploadedFile::fake()->create('lesson-content.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('admin.lessons.edit', [$course, $lesson, 'tab' => 'pdf']));

        $lesson->refresh();
        $asset = $lesson->mediaAssets()->firstOrFail();
        $this->assertSame('lesson_pdf', $asset->kind);
        $this->assertTrue($lesson->content_pdf_enabled);
        $this->assertSame($asset->id, $lesson->content_pdf_media_id);
        $this->assertSame('lesson-content', $lesson->content_pdf_title);
        Storage::disk('local')->assertExists($asset->path);
    }

    public function test_admin_pdf_tab_rejects_non_pdf_uploads_with_validation_error(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['is_published' => false]);
        $lesson = $course->lessons()->create(['slug' => 'bad-pdf-upload', 'title' => 'درس PDF', 'title_en' => 'PDF lesson', 'duration_minutes' => 10, 'position' => 1]);

        $this->actingAs($admin)->from(route('admin.lessons.edit', [$course, $lesson, 'tab' => 'pdf']))->post(route('admin.lesson-pdf.store', [$course, $lesson]), [
            'file' => UploadedFile::fake()->create('not-a-pdf.txt', 5, 'text/plain'),
        ])->assertRedirect(route('admin.lessons.edit', [$course, $lesson, 'tab' => 'pdf']))
            ->assertSessionHasErrors('file');

        $this->assertFalse($lesson->fresh()->content_pdf_enabled);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_admin_can_switch_active_lesson_pdf_from_pdf_tab(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['is_published' => false]);
        $lesson = $course->lessons()->create(['slug' => 'switch-pdf', 'title' => 'درس PDF', 'title_en' => 'PDF lesson', 'duration_minutes' => 10, 'position' => 1]);
        $first = MediaAsset::create(['lesson_id' => $lesson->id, 'kind' => 'lesson_pdf', 'disk' => 'local', 'path' => 'media-library/first.pdf', 'original_name' => 'first.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1024]);
        $second = MediaAsset::create(['lesson_id' => $lesson->id, 'kind' => 'lesson_pdf', 'disk' => 'local', 'path' => 'media-library/second.pdf', 'original_name' => 'second.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 2048]);
        $lesson->update(['content_pdf_enabled' => true, 'content_pdf_media_id' => $first->id, 'content_pdf_title' => 'first']);

        $this->actingAs($admin)->get(route('admin.lessons.edit', [$course, $lesson]).'?tab=pdf')
            ->assertOk()
            ->assertSee('first.pdf')
            ->assertSee('second.pdf')
            ->assertSee('استخدام هذا الملف');

        $this->put(route('admin.lessons.update', [$course, $lesson]), [
            'content_pdf_enabled' => 1,
            'content_pdf_media_id' => $second->id,
        ])->assertRedirect();

        $lesson->refresh();

        $this->assertSame($second->id, $lesson->content_pdf_media_id);
        $this->assertSame('second', $lesson->content_pdf_title);
        $this->assertTrue($lesson->content_pdf_enabled);
    }

    public function test_lesson_resource_prefers_selected_pdf_media_over_external_pdf_url(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['is_published' => true]);
        $lesson = $course->lessons()->create([
            'slug' => 'resource-pdf',
            'title' => 'درس PDF',
            'title_en' => 'PDF lesson',
            'duration_minutes' => 10,
            'position' => 1,
            'status' => 'published',
            'is_preview' => true,
            'content_pdf_enabled' => true,
            'content_pdf_url' => 'https://example.com/old.pdf',
            'content_pdf_title' => 'Old PDF title',
        ]);
        $asset = MediaAsset::create(['lesson_id' => $lesson->id, 'kind' => 'lesson_pdf', 'disk' => 'local', 'path' => 'media-library/current.pdf', 'original_name' => 'current.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1024]);
        $lesson->update(['content_pdf_media_id' => $asset->id]);

        $request = Request::create('/api/courses/'.$course->slug, 'GET');
        $request->setUserResolver(fn () => $admin);
        $data = (new LessonResource($lesson->fresh()->load('mediaAssets.libraryItem')))->toArray($request);

        $this->assertSame($asset->id, $data['contentPdf']['mediaId']);
        $this->assertNull($data['contentPdf']['url']);
        $this->assertSame('current', $data['contentPdf']['title']);
    }

    public function test_admin_lesson_partial_forms_do_not_reset_unsubmitted_toggles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['is_published' => false]);
        $lesson = $course->lessons()->create([
            'slug' => 'partial-lesson',
            'title' => 'درس مجزأ',
            'title_en' => 'Partial lesson',
            'duration_minutes' => 20,
            'position' => 1,
            'is_preview' => true,
            'content_pdf_enabled' => true,
            'workbook_enabled' => true,
            'allow_print' => true,
            'allow_pdf_download' => false,
            'include_workbook_cover' => true,
            'include_workbook_info' => true,
            'include_workbook_toc' => true,
            'include_workbook_answer_key' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.lessons.update', [$course, $lesson]), [
            'summary' => 'ملخص محفوظ من نموذج أهداف التعلم فقط.',
        ])->assertRedirect();

        $lesson->refresh();
        $this->assertSame('ملخص محفوظ من نموذج أهداف التعلم فقط.', $lesson->summary);
        $this->assertTrue($lesson->is_preview);
        $this->assertTrue($lesson->content_pdf_enabled);
        $this->assertTrue($lesson->workbook_enabled);
        $this->assertTrue($lesson->allow_print);
        $this->assertFalse($lesson->allow_pdf_download);
        $this->assertTrue($lesson->include_workbook_cover);
        $this->assertTrue($lesson->include_workbook_info);
        $this->assertTrue($lesson->include_workbook_toc);
        $this->assertTrue($lesson->include_workbook_answer_key);
    }

    public function test_admin_can_edit_and_reorder_questions_and_view_attempt_reports(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['is_published' => false]);
        $assessment = $course->assessment()->create(['duration_minutes' => 15, 'passing_score' => 70, 'is_published' => false]);
        $first = $assessment->questions()->create(['topic' => 'One', 'topic_ar' => 'واحد', 'prompt' => 'First?', 'prompt_ar' => 'الأول؟', 'options' => ['Yes', 'No'], 'options_ar' => ['نعم', 'لا'], 'correct_option' => 0, 'explanation' => 'Yes', 'explanation_ar' => 'نعم', 'position' => 1]);
        $second = $assessment->questions()->create(['topic' => 'Two', 'topic_ar' => 'اثنان', 'prompt' => 'Second?', 'prompt_ar' => 'الثاني؟', 'options' => ['Yes', 'No'], 'options_ar' => ['نعم', 'لا'], 'correct_option' => 1, 'explanation' => 'No', 'explanation_ar' => 'لا', 'position' => 2]);

        $this->actingAs($admin)->put(route('admin.questions.update', [$course, $first]), [
            'topic' => 'Updated', 'topic_ar' => 'محدث', 'prompt' => 'Updated?', 'prompt_ar' => 'محدث؟',
            'options_text' => "True\nFalse", 'options_ar_text' => "صحيح\nخطأ", 'correct_option' => 0,
            'explanation' => 'Correct', 'explanation_ar' => 'صحيح', 'position' => 1,
        ])->assertRedirect();
        $this->post(route('admin.questions.move', [$course, $second, 'up']))->assertRedirect();
        $this->get(route('admin.assessment.attempts', $course))->assertOk()->assertSee('نتائج التقييم');

        $this->assertDatabaseHas('assessment_questions', ['id' => $first->id, 'topic' => 'Updated', 'position' => 2]);
        $this->assertDatabaseHas('assessment_questions', ['id' => $second->id, 'position' => 1]);
    }

    public function test_admin_can_review_and_refund_a_completed_local_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create();
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);
        $order = $this->completedOrder($student, $course);

        $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk()->assertSee($student->email)->assertSee($course->title);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee($order->public_id);
        $this->post(route('admin.orders.refund', $order), ['reason' => 'طلب الطالب استرداد المبلغ بالكامل.'])->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'refunded']);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('enrollment_events', ['action' => 'cancelled']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.refunded']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $student->id, 'title' => 'Payment refunded']);
    }

    public function test_refund_preserves_access_when_another_completed_order_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create();
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);
        $refunded = $this->completedOrder($student, $course);
        $this->completedOrder($student, $course);

        $this->actingAs($admin)->post(route('admin.orders.refund', $refunded), ['reason' => 'تم تكرار عملية الدفع لنفس الدورة.'])->assertRedirect();

        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
    }

    public function test_admin_can_preview_course_and_use_the_shared_publishing_checklist(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['is_published' => false, 'description' => null, 'description_en' => null]);

        $this->actingAs($admin)->get(route('admin.courses.readiness', $course))
            ->assertOk()->assertSee('قائمة جاهزية النشر')->assertSee('أضف درساً واحداً على الأقل قبل النشر.');
        $this->post(route('admin.courses.state', [$course, 'publish']))->assertSessionHasErrors('course');

        $course->lessons()->create(['slug' => 'preview', 'title' => 'معاينة', 'title_en' => 'Preview', 'duration_minutes' => 15, 'position' => 1, 'is_preview' => true]);
        $this->get(route('admin.courses.preview', $course))->assertOk()->assertSee('وضع المعاينة الإدارية')->assertSee('معاينة');
        $this->post(route('admin.courses.state', [$course, 'publish']))->assertRedirect();

        $this->assertTrue($course->fresh()->is_published);
    }

    public function test_course_preview_is_protected_from_students(): void
    {
        $course = Course::factory()->create();

        $this->actingAs(User::factory()->create(['role' => 'student']))->get(route('admin.courses.preview', $course))->assertForbidden();
    }

    public function test_course_catalog_has_operational_filters_and_readiness_cards(): void
    {
        $editor = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor']);
        $published = Course::factory()->create(['title' => 'الدورة المنشورة للاختبار', 'is_published' => true, 'archived_at' => null, 'grade' => 'grade-1', 'semester' => 'semester-1']);
        $published->lessons()->create(['slug' => 'catalog-preview', 'title' => 'درس المعاينة', 'title_en' => 'Preview lesson', 'duration_minutes' => 15, 'position' => 1, 'is_preview' => true]);
        Course::factory()->create(['title' => 'مسودة كتالوج الدورات', 'is_published' => false, 'archived_at' => null]);
        Course::factory()->create(['title' => 'دورة مؤرشفة للاختبار', 'is_published' => false, 'archived_at' => now()]);

        $this->actingAs($editor)->get(route('admin.courses.index', ['status' => 'published', 'grade' => 'grade-1']))
            ->assertOk()
            ->assertSee('الدورة المنشورة للاختبار')
            ->assertSee('جاهزية المحتوى')
            ->assertDontSee('مسودة كتالوج الدورات')
            ->assertDontSee('دورة مؤرشفة للاختبار');

        $this->get(route('admin.courses.index', ['status' => 'unsupported']))->assertSessionHasErrors('status');
    }

    public function test_admin_can_view_real_learning_and_revenue_analytics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['title' => 'دورة التحليلات', 'title_en' => 'Analytics course']);
        $lesson = $course->lessons()->create([
            'slug' => 'analytics-lesson', 'title' => 'درس التحليلات', 'title_en' => 'Analytics lesson',
            'duration_minutes' => 20, 'position' => 1,
        ]);
        $assessment = $course->assessment()->create(['duration_minutes' => 15, 'passing_score' => 70, 'is_published' => true]);
        $attempt = AssessmentAttempt::create([
            'assessment_id' => $assessment->id, 'user_id' => $student->id, 'answers' => [],
            'score' => 80, 'passed' => true, 'submitted_at' => now(),
        ]);

        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
        LessonCompletion::create(['user_id' => $student->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
        $this->completedOrder($student, $course);
        Certificate::create([
            'public_id' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'assessment_attempt_id' => $attempt->id, 'issued_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.analytics.index', ['period' => 7]))
            ->assertOk()
            ->assertSee('التحليلات والتقارير')
            ->assertSee('دورة التحليلات')
            ->assertSee('500')
            ->assertSee('80%');
    }

    public function test_admin_can_export_analytics_and_students_cannot_access_reports(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->get(route('admin.analytics.export', ['period' => 30]))
            ->assertOk()
            ->assertDownload();
        $this->actingAs($student)->get(route('admin.analytics.index'))->assertForbidden();
        $this->get(route('admin.analytics.export'))->assertForbidden();
    }

    public function test_analytics_rejects_an_unsupported_reporting_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.analytics.index', ['period' => 14]))
            ->assertRedirect()
            ->assertSessionHasErrors('period');
    }

    public function test_admin_can_moderate_a_review_and_course_rating_is_recalculated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['rating' => 0]);
        $review = CourseReview::create([
            'user_id' => $student->id, 'course_id' => $course->id, 'rating' => 5,
            'comment' => 'شرح واضح وتدريبات عملية مفيدة جداً.', 'status' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('admin.trust.index'))
            ->assertOk()->assertSee('الثقة والشهادات')->assertSee($review->comment);
        $this->post(route('admin.trust.reviews.moderate', $review), [
            'status' => 'published', 'moderation_note' => 'تقييم موثوق من طالب مسجل.',
        ])->assertRedirect();

        $this->assertDatabaseHas('course_reviews', ['id' => $review->id, 'status' => 'published', 'moderated_by' => $admin->id]);
        $this->assertSame('5.0', $course->fresh()->rating);
        $this->assertDatabaseHas('audit_logs', ['action' => 'course_review.published']);
    }

    public function test_revoked_certificate_can_no_longer_be_publicly_verified(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create();
        $assessment = $course->assessment()->create(['duration_minutes' => 15, 'passing_score' => 70, 'is_published' => true]);
        $attempt = AssessmentAttempt::create([
            'assessment_id' => $assessment->id, 'user_id' => $student->id, 'answers' => [],
            'score' => 90, 'passed' => true, 'submitted_at' => now(),
        ]);
        $certificate = Certificate::create([
            'public_id' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'assessment_attempt_id' => $attempt->id, 'issued_at' => now(),
        ]);

        $this->get('/api/certificates/verify/'.$certificate->public_id)->assertOk();
        $this->actingAs($admin)->post(route('admin.trust.certificates.revoke', $certificate), [
            'reason' => 'تم إصدار الشهادة بناءً على محاولة غير صالحة.',
        ])->assertRedirect();

        $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'revoked_by' => $admin->id]);
        $this->get('/api/certificates/verify/'.$certificate->public_id)->assertStatus(410)->assertJsonPath('status', 'revoked');
        $this->assertDatabaseHas('audit_logs', ['action' => 'certificate.revoked']);
    }

    public function test_admin_can_manage_an_instructor_and_assign_the_profile_to_a_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['instructor' => 'اسم قديم', 'instructor_en' => 'Old name']);

        $this->actingAs($admin)->post(route('admin.instructors.store'), [
            'name' => 'أحمد محمد', 'name_en' => 'Ahmed Mohamed',
            'headline' => 'مدرس برمجة', 'headline_en' => 'Programming instructor',
            'email' => 'instructor@example.com', 'bio' => 'خبرة في تعليم البرمجة.', 'bio_en' => 'Experienced programming educator.',
        ])->assertRedirect();
        $instructor = InstructorProfile::where('email', 'instructor@example.com')->firstOrFail();

        $this->post(route('admin.instructors.assign', $course), ['instructor_profile_id' => $instructor->id])->assertRedirect();
        $this->put(route('admin.instructors.update', $instructor), [
            'name' => 'أحمد محمود', 'name_en' => 'Ahmed Mahmoud', 'email' => 'instructor@example.com',
        ])->assertRedirect();

        $this->assertDatabaseHas('courses', [
            'id' => $course->id, 'instructor_profile_id' => $instructor->id,
            'instructor' => 'أحمد محمود', 'instructor_en' => 'Ahmed Mahmoud',
        ]);
        $this->get(route('admin.instructors.index'))->assertOk()->assertSee('أحمد محمود')->assertSee('instructor@example.com');
        $this->assertDatabaseHas('audit_logs', ['action' => 'course.instructor_assigned']);
    }

    public function test_instructor_management_is_admin_only_and_profiles_can_be_deactivated(): void
    {
        $instructor = InstructorProfile::create(['name' => 'مدرس', 'name_en' => 'Instructor', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.instructors.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.instructors.toggle', $instructor))->assertRedirect();

        $this->assertFalse($instructor->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'instructor.deactivated']);
    }

    public function test_instructor_manager_can_link_teacher_accounts_and_assign_courses_idempotently(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'admin_role' => 'instructor_manager']);
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER, 'is_active' => true]);
        $profile = InstructorProfile::create(['name' => 'مدرس', 'name_en' => 'Teacher', 'is_active' => true]);
        $course = Course::factory()->create();

        $this->actingAs($manager)->post(route('admin.instructors.account.link', $profile), ['user_id' => $teacher->id])->assertRedirect();
        $this->post(route('admin.instructors.teacher.assign', $course), ['teacher_id' => $teacher->id])->assertRedirect();
        $this->post(route('admin.instructors.teacher.assign', $course), ['teacher_id' => $teacher->id])->assertRedirect();

        $this->assertSame($teacher->id, $profile->refresh()->user_id);
        $this->assertDatabaseCount('teacher_course_assignments', 1);
        $this->delete(route('admin.instructors.teacher.unassign', [$course, $teacher]))->assertRedirect();
        $this->assertDatabaseHas('teacher_course_assignments', ['teacher_id' => $teacher->id, 'course_id' => $course->id, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'teacher.course_assigned']);
    }

    public function test_finance_staff_cannot_manage_teacher_assignments(): void
    {
        $finance = User::factory()->create(['role' => 'admin', 'admin_role' => 'finance_manager']);
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $course = Course::factory()->create();

        $this->actingAs($finance)->post(route('admin.instructors.teacher.assign', $course), ['teacher_id' => $teacher->id])->assertForbidden();
        $this->assertDatabaseCount('teacher_course_assignments', 0);
    }

    public function test_admin_can_publish_a_course_announcement_only_to_enrolled_students(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $recipient = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $parent = User::factory()->create(['role' => 'parent']);
        $course = Course::factory()->create();
        Enrollment::create(['user_id' => $recipient->id, 'course_id' => $course->id, 'enrolled_at' => now()]);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Assessment reminder', 'title_ar' => 'تذكير بالتقييم',
            'message' => 'Complete the assessment before Thursday.', 'message_ar' => 'أكمل التقييم قبل يوم الخميس.',
            'target_type' => 'course', 'course_id' => $course->id, 'action_url' => '/courses/'.$course->id,
            'is_banner' => '1',
            'action' => 'publish',
        ])->assertRedirect();

        $announcement = Announcement::firstOrFail();
        $this->assertSame('published', $announcement->status);
        $this->assertTrue($announcement->is_banner);
        $this->assertDatabaseHas('user_notifications', ['announcement_id' => $announcement->id, 'user_id' => $recipient->id]);
        $this->assertDatabaseMissing('user_notifications', ['announcement_id' => $announcement->id, 'user_id' => $otherStudent->id]);
        $this->assertDatabaseMissing('user_notifications', ['announcement_id' => $announcement->id, 'user_id' => $parent->id]);
        $this->get(route('admin.announcements.index'))->assertOk()->assertSee('تذكير بالتقييم')->assertSee('1 مستلم');
    }

    public function test_admin_can_publish_an_announcement_to_one_cohort_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create();
        $member->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now(), 'status' => 'active']);
        $cohort = Cohort::create(['course_id' => $course->id, 'name' => 'Morning', 'code' => 'morning', 'status' => 'active']);
        $cohort->students()->attach($member->id, ['added_by' => $admin->id]);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Cohort update', 'title_ar' => 'تحديث الفصل', 'message' => 'For the morning class.', 'message_ar' => 'لطلاب الفصل الصباحي.',
            'target_type' => 'cohort', 'cohort_id' => $cohort->id, 'action' => 'publish',
        ])->assertRedirect();
        $announcement = Announcement::firstOrFail();
        $this->assertDatabaseHas('user_notifications', ['announcement_id' => $announcement->id, 'user_id' => $member->id]);
        $this->assertDatabaseMissing('user_notifications', ['announcement_id' => $announcement->id, 'user_id' => $other->id]);
    }

    public function test_due_announcements_are_dispatched_once_by_the_scheduler(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $announcement = Announcement::create([
            'created_by' => $admin->id, 'title' => 'Scheduled', 'title_ar' => 'إعلان مجدول',
            'message' => 'Scheduled message.', 'message_ar' => 'رسالة مجدولة.', 'target_type' => 'students',
            'status' => 'scheduled', 'publish_at' => now()->subMinute(),
        ]);

        $this->artisan('announcements:dispatch')->assertSuccessful();
        $this->artisan('announcements:dispatch')->assertSuccessful();

        $this->assertDatabaseCount('user_notifications', 1);
        $this->assertDatabaseHas('user_notifications', ['announcement_id' => $announcement->id, 'user_id' => $student->id]);
        $this->assertSame('published', $announcement->fresh()->status);
    }

    public function test_announcement_management_is_admin_only_and_schedule_requires_a_future_date(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.announcements.index'))->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Scheduled', 'title_ar' => 'مجدول', 'message' => 'Message content',
            'message_ar' => 'محتوى الرسالة', 'target_type' => 'all', 'action' => 'schedule',
        ])->assertSessionHasErrors('publish_at');
    }

    public function test_admin_can_enroll_suspend_extend_and_transfer_a_student(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $source = Course::factory()->create();
        $target = Course::factory()->create();

        $this->actingAs($admin)->post(route('admin.enrollments.store'), [
            'user_id' => $student->id, 'course_id' => $source->id, 'reason' => 'تسجيل يدوي من الإدارة.',
        ])->assertRedirect();
        $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $source->id)->firstOrFail();
        $this->post(route('admin.enrollments.action', $enrollment), ['action' => 'suspend', 'reason' => 'إيقاف مؤقت بطلب ولي الأمر.'])->assertRedirect();
        $this->assertFalse($enrollment->fresh()->hasAccess());

        $this->post(route('admin.enrollments.action', $enrollment), [
            'action' => 'extend', 'reason' => 'تمديد لاستكمال المحتوى.', 'access_expires_at' => now()->addMonth()->toDateTimeString(),
        ])->assertRedirect();
        $this->assertTrue($enrollment->fresh()->hasAccess());

        $this->post(route('admin.enrollments.transfer', $enrollment), [
            'current_course' => $source->id, 'course_id' => $target->id, 'reason' => 'الانتقال إلى المسار الأنسب.',
        ])->assertRedirect();

        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $target->id, 'status' => 'active', 'source' => 'transfer']);
        $this->assertDatabaseHas('enrollment_events', ['enrollment_id' => $enrollment->id, 'action' => 'transferred_out']);
    }

    public function test_suspended_or_expired_enrollment_cannot_access_learning_content(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create();
        $lesson = $course->lessons()->create(['slug' => 'protected', 'title' => 'محمي', 'title_en' => 'Protected', 'duration_minutes' => 10, 'position' => 1]);
        $enrollment = Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'enrolled_at' => now(), 'status' => 'suspended']);

        $this->actingAs($student)->postJson('/api/courses/'.$course->id.'/lessons/'.$lesson->slug.'/complete')->assertForbidden();
        $enrollment->update(['status' => 'active', 'access_expires_at' => now()->subMinute()]);
        $this->postJson('/api/courses/'.$course->id.'/lessons/'.$lesson->slug.'/complete')->assertForbidden();
        $this->getJson('/api/me/learning')->assertOk()->assertJsonMissing(['enrolledIds' => [$course->id]]);
    }

    public function test_admin_can_bulk_import_enrollments_from_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'email' => 'bulk@example.com']);
        $course = Course::factory()->create(['id' => 'bulk-course']);
        $csv = UploadedFile::fake()->createWithContent('enrollments.csv', "email,course_id,expires_at\nbulk@example.com,bulk-course,\nmissing@example.com,bulk-course,\n");

        $this->actingAs($admin)->post(route('admin.enrollments.import'), ['csv' => $csv])
            ->assertRedirect()->assertSessionHas('success', 'تم استيراد 1 تسجيلات.');

        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $course->id, 'source' => 'csv', 'status' => 'active']);
    }

    public function test_super_admin_can_create_staff_with_a_restricted_role(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin', 'admin_role' => null]);

        $this->actingAs($superAdmin)->post(route('admin.staff.store'), [
            'name' => 'Support Agent', 'email' => 'support@example.com',
            'admin_role' => 'support_agent', 'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!',
        ])->assertRedirect();

        $staff = User::where('email', 'support@example.com')->firstOrFail();
        $this->assertSame('support_agent', $staff->adminRole());
        $this->assertTrue($staff->hasAdminPermission('users.manage'));
        $this->assertFalse($staff->hasAdminPermission('finance.manage'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.created', 'auditable_id' => (string) $staff->id]);
    }

    public function test_support_staff_can_only_access_allowed_admin_modules(): void
    {
        $staff = User::factory()->create(['role' => 'admin', 'admin_role' => 'support_agent']);

        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('الطلاب والأسر')->assertSee('التسجيلات والصلاحيات')
            ->assertDontSee('الطلبات والمدفوعات')->assertDontSee('الدورات والمحتوى');
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.enrollments.index'))->assertOk();
        $this->get(route('admin.orders.index'))->assertForbidden();
        $this->get(route('admin.courses.index'))->assertForbidden();
        $this->get(route('admin.staff.index'))->assertForbidden();
    }

    public function test_users_index_defaults_to_table_and_can_switch_to_grid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'student', 'name' => 'Table Student']);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('users-admin-table', false)
            ->assertDontSee('users-admin-grid', false)
            ->assertSee('aria-current="true"', false);

        $this->get(route('admin.users.index', ['view' => 'grid', 'role' => 'student']))
            ->assertOk()
            ->assertSee('users-admin-grid', false)
            ->assertDontSee('users-admin-table', false)
            ->assertSee('Table Student')
            ->assertSee('name="view" value="grid"', false);
    }

    public function test_users_index_rejects_an_unknown_view_mode(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['view' => 'cards']))
            ->assertSessionHasErrors('view');
    }

    public function test_learning_content_indexes_default_to_table_and_support_grid_view(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pages = [
            ['admin.courses.index', 'courses-list-table', 'course-admin-grid'],
            ['admin.question-bank.index', 'questions-list-table', 'question-bank-grid'],
            ['admin.media-library.index', 'media-list-table', 'media-library-grid'],
            ['admin.instructors.index', 'instructors-list-table', 'instructor-grid'],
        ];

        foreach ($pages as [$route, $tableClass, $gridClass]) {
            $this->actingAs($admin)->get(route($route))
                ->assertOk()
                ->assertSee($tableClass, false)
                ->assertDontSee($gridClass, false);

            $this->get(route($route, ['view' => 'grid']))
                ->assertOk()
                ->assertSee($gridClass, false)
                ->assertDontSee($tableClass, false)
                ->assertSee('name="view" value="grid"', false);
        }
    }

    public function test_learning_content_indexes_validate_the_view_mode(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['admin.courses.index', 'admin.question-bank.index', 'admin.media-library.index', 'admin.instructors.index'] as $route) {
            $this->actingAs($admin)->get(route($route, ['view' => 'cards']))
                ->assertSessionHasErrors('view');
        }
    }

    public function test_dashboard_is_task_oriented_and_hides_finance_data_from_content_staff(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => false, 'description' => null, 'description_en' => null]);
        $order = $student->orders()->create([
            'public_id' => fake()->uuid(), 'course_id' => $course->id, 'amount' => 987654,
            'original_amount' => 987654, 'currency' => 'EGP', 'status' => 'submitted',
            'payment_provider' => 'manual_transfer', 'payment_method' => 'instapay',
            'session_token_hash' => hash('sha256', 'dashboard-order'),
        ]);
        ManualPaymentSubmission::create([
            'order_id' => $order->id, 'user_id' => $student->id, 'channel' => 'instapay',
            'transaction_id' => 'DASHBOARD-PAYMENT', 'sender_identifier' => 'student@instapay',
            'transferred_at' => now(), 'submitted_amount' => 987654,
            'receipt_path' => 'receipts/dashboard.jpg', 'receipt_original_name' => 'dashboard.jpg',
            'receipt_mime' => 'image/jpeg', 'receipt_size' => 10,
            'receipt_sha256' => hash('sha256', 'dashboard-receipt'), 'status' => 'submitted',
        ]);

        $editor = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor']);
        $this->actingAs($editor)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('دورات غير جاهزة للنشر')
            ->assertDontSee('إيراد آخر 30 يوماً')
            ->assertDontSee('مراجعة التحويلات الجديدة')
            ->assertDontSee('987,654');

        $finance = User::factory()->create(['role' => 'admin', 'admin_role' => 'finance_manager']);
        $this->actingAs($finance)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('مدير المالية')
            ->assertSee('تحويلات تنتظر القرار')
            ->assertSee('مراجعة التحويلات الجديدة')
            ->assertDontSee('دورات غير جاهزة للنشر');
    }

    public function test_content_editor_cannot_perform_finance_or_staff_actions(): void
    {
        $editor = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor']);

        $this->actingAs($editor)->get(route('admin.courses.index'))->assertOk();
        $this->get(route('admin.instructors.index'))->assertOk();
        $this->get(route('admin.analytics.index'))->assertOk();
        $this->get(route('admin.orders.index'))->assertForbidden();
        $this->get(route('admin.staff.index'))->assertForbidden();
        $this->getJson('/api/admin/overview')->assertForbidden();
    }

    public function test_staff_manager_cannot_change_or_disable_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.staff.update', $admin), ['admin_role' => 'finance_manager'])->assertStatus(409);
        $this->post(route('admin.staff.toggle', $admin))->assertStatus(409);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_content_admin_can_create_and_import_a_bilingual_bank_question(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['is_published' => false]);
        $assessment = $course->assessment()->create(['duration_minutes' => 15, 'passing_score' => 70, 'is_published' => false]);

        $this->actingAs($admin)->post(route('admin.question-bank.store'), [
            'type' => 'true_false', 'difficulty' => 'easy', 'topic' => 'Variables', 'topic_ar' => 'المتغيرات',
            'prompt' => 'A variable stores a value.', 'prompt_ar' => 'يخزن المتغير قيمة.', 'correct_option' => 0,
            'explanation' => 'This is the purpose of a variable.', 'explanation_ar' => 'هذه هي وظيفة المتغير.',
        ])->assertRedirect();
        $bankQuestion = QuestionBankQuestion::firstOrFail();

        $this->post(route('admin.question-bank.import', $course), ['question_ids' => [$bankQuestion->id]])->assertRedirect();
        $this->post(route('admin.assessment.delivery', $course), ['randomize_questions' => 1, 'question_limit' => 1])->assertRedirect();

        $this->assertDatabaseHas('assessment_questions', ['assessment_id' => $assessment->id, 'question_bank_question_id' => $bankQuestion->id, 'type' => 'true_false']);
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'randomize_questions' => true, 'question_limit' => 1]);
        $this->get(route('admin.question-bank.assessment', $course))->assertOk()->assertSee('يخزن المتغير قيمة.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'assessment_question.imported']);
    }

    public function test_content_admin_can_create_named_question_bank_quiz_and_add_questions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.question-bank.store'), [
            'type' => 'true_false',
            'difficulty' => 'easy',
            'topic' => 'Variables',
            'topic_ar' => 'المتغيرات',
            'prompt' => 'A variable stores a value.',
            'prompt_ar' => 'يخزن المتغير قيمة.',
            'correct_option' => 0,
            'explanation' => 'This is the purpose of a variable.',
            'explanation_ar' => 'هذه هي وظيفة المتغير.',
        ])->assertRedirect();

        $question = QuestionBankQuestion::firstOrFail();

        $this->post(route('admin.question-bank.quizzes.store'), [
            'name_ar' => 'اختبار درس 1 وحدة 1',
            'name_en' => 'Lesson 1 Unit 1 Quiz',
            'type' => 'required',
            'passing_score' => 75,
            'description_ar' => 'اختبار إلزامي بعد الدرس.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $quiz = QuestionBankQuiz::firstOrFail();

        $this->post(route('admin.question-bank.quizzes.questions.attach', $quiz), [
            'question_id' => $question->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('question_bank_quizzes', ['name_ar' => 'اختبار درس 1 وحدة 1', 'type' => 'required', 'is_required' => true]);
        $this->assertDatabaseHas('question_bank_question_quiz', ['question_bank_quiz_id' => $quiz->id, 'question_bank_question_id' => $question->id]);
        $this->get(route('admin.question-bank.index', ['tab' => 'quizzes']))->assertOk()->assertSee('اختبار درس 1 وحدة 1');

        $this->post(route('admin.question-bank.quizzes.duplicate', $quiz))->assertRedirect();
        $this->assertDatabaseHas('question_bank_quizzes', ['name_ar' => 'نسخة من اختبار درس 1 وحدة 1', 'is_active' => false]);
        $this->assertSame(2, QuestionBankQuiz::count());

        $this->post(route('admin.question-bank.duplicate', $question))->assertRedirect();
        $this->assertDatabaseHas('question_bank_questions', ['prompt_ar' => 'نسخة من يخزن المتغير قيمة.']);

        $this->delete(route('admin.question-bank.quizzes.questions.detach', [$quiz, $question]))->assertRedirect();
        $this->assertDatabaseMissing('question_bank_question_quiz', ['question_bank_quiz_id' => $quiz->id, 'question_bank_question_id' => $question->id]);
        $this->assertDatabaseHas('question_bank_questions', ['id' => $question->id]);
    }

    public function test_admin_can_remove_quiz_assignment_from_lesson_without_deleting_bank_quiz(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();
        $lesson = $course->lessons()->create(['slug' => 'quiz-lesson', 'title' => 'درس الاختبار', 'title_en' => 'Quiz lesson', 'duration_minutes' => 10, 'position' => 1]);
        $quiz = QuestionBankQuiz::create([
            'name_ar' => 'اختبار مرتبط',
            'name_en' => 'Linked quiz',
            'type' => 'training',
            'passing_score' => 70,
            'is_required' => false,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.learning-paths.trainings.store', ['lesson', $lesson->id]), [
            'question_bank_quiz_id' => $quiz->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $training = Training::firstOrFail();

        $this->get(route('admin.lessons.edit', [$course, $lesson]).'?tab=questions')->assertOk()->assertSee('حذف الارتباط');
        $this->delete(route('admin.learning-paths.trainings.destroy', $training))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('trainings', ['id' => $training->id]);
        $this->assertDatabaseHas('question_bank_quizzes', ['id' => $quiz->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'learning_path.training_deleted']);
    }

    public function test_admin_cannot_publish_lesson_quiz_assignment_without_questions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();
        $lesson = $course->lessons()->create(['slug' => 'empty-quiz-lesson', 'title' => 'درس اختبار فارغ', 'title_en' => 'Empty quiz lesson', 'duration_minutes' => 10, 'position' => 1]);
        $training = $lesson->trainings()->create([
            'type' => 'exercise',
            'title_ar' => 'اختبار فارغ',
            'title_en' => 'Empty quiz',
            'passing_score' => 70,
            'position' => 1,
            'status' => 'draft',
            'is_required' => false,
        ]);

        $this->actingAs($admin)->get(route('admin.lessons.edit', [$course, $lesson]).'?tab=questions')
            ->assertOk()
            ->assertSee('أضف أسئلة أولاً')
            ->assertDontSee('> نشر</button>', false);

        $this->post(route('admin.learning-paths.state', ['training', $training->id]), ['status' => 'published'])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('trainings', ['id' => $training->id, 'status' => 'draft']);
    }

    public function test_admin_can_reuse_one_library_file_across_multiple_lessons(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['is_published' => false]);
        $first = $course->lessons()->create(['slug' => 'one', 'title' => 'الأول', 'title_en' => 'One', 'duration_minutes' => 10, 'position' => 1]);
        $second = $course->lessons()->create(['slug' => 'two', 'title' => 'الثاني', 'title_en' => 'Two', 'duration_minutes' => 10, 'position' => 2]);

        $this->actingAs($admin)->post(route('admin.media-library.store'), [
            'kind' => 'resource', 'file' => UploadedFile::fake()->create('guide.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $item = MediaLibraryItem::firstOrFail();
        $this->post(route('admin.media-library.attach', $item), ['lesson_id' => $first->id])->assertRedirect();
        $this->post(route('admin.media-library.attach', $item), ['lesson_id' => $second->id])->assertRedirect();

        $this->assertSame(2, $item->attachments()->count());
        $this->assertDatabaseCount('media_library_items', 1);
        Storage::disk('local')->assertExists($item->path);
        $this->get(route('admin.media-library.index'))->assertOk()->assertSee('guide.pdf')->assertSee('2 ارتباطات');
    }

    public function test_library_integrity_check_and_orphan_archive_are_safe(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $item = MediaLibraryItem::create([
            'uploaded_by' => $admin->id, 'kind' => 'resource', 'disk' => 'local', 'path' => 'media-library/missing.pdf',
            'original_name' => 'missing.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100, 'processing_status' => 'ready',
        ]);

        $this->actingAs($admin)->post(route('admin.media-library.verify', $item))->assertRedirect();
        $this->assertSame('failed', $item->fresh()->processing_status);
        $this->post(route('admin.media-library.archive', $item))->assertRedirect();
        $this->assertNotNull($item->fresh()->archived_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'media_library.archived']);
    }

    public function test_admin_can_deep_duplicate_course_content_without_student_data(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $source = Course::factory()->create(['id' => 'source-course']);
        $unit = Unit::create(['course_id' => $source->id, 'slug' => 'unit-one', 'title_ar' => 'الوحدة الأولى', 'title_en' => 'Unit one', 'position' => 1, 'status' => 'published']);
        $lesson = $source->lessons()->create(['unit_id' => $unit->id, 'slug' => 'intro', 'title' => 'مقدمة', 'title_en' => 'Intro', 'duration_minutes' => 10, 'position' => 1]);
        Assignment::create(['lesson_id' => $lesson->id, 'title' => 'مهمة', 'title_en' => 'Assignment', 'description_en' => 'Work', 'due_at' => now()->addDay(), 'is_published' => true, 'position' => 1]);
        $item = MediaLibraryItem::create(['uploaded_by' => $admin->id, 'kind' => 'resource', 'disk' => 'local', 'path' => 'media-library/shared.pdf', 'original_name' => 'shared.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10, 'processing_status' => 'ready']);
        $lesson->mediaAssets()->create(['media_library_item_id' => $item->id, 'kind' => 'resource', 'disk' => 'local', 'path' => $item->path, 'original_name' => $item->original_name, 'mime_type' => $item->mime_type, 'size_bytes' => $item->size_bytes]);
        $assessment = $source->assessment()->create(['duration_minutes' => 10, 'passing_score' => 70, 'is_published' => true]);
        $assessment->questions()->create(['topic' => 'One', 'topic_ar' => 'واحد', 'prompt' => 'One?', 'prompt_ar' => 'واحد؟', 'options' => ['Yes', 'No'], 'options_ar' => ['نعم', 'لا'], 'correct_option' => 0, 'explanation' => 'Yes', 'explanation_ar' => 'نعم', 'position' => 1]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $source->id, 'enrolled_at' => now()]);

        $this->actingAs($admin)->post(route('admin.courses.duplicate', $source), ['id' => 'copied-course', 'title' => 'الدورة المنسوخة', 'title_en' => 'Copied course'])->assertRedirect();
        $copy = Course::findOrFail('copied-course');

        $this->assertFalse($copy->is_published);
        $this->assertSame($source->id, $copy->source_course_id);
        $this->assertCount(1, $copy->lessons);
        $this->assertCount(1, $copy->units);
        $this->assertSame($copy->units->first()->id, $copy->lessons->first()->unit_id);
        $this->assertFalse($copy->lessons->first()->assignments->first()->is_published);
        $this->assertSame($item->id, $copy->lessons->first()->mediaAssets->first()->media_library_item_id);
        $this->assertCount(1, $copy->assessment->questions);
        $this->assertSame(0, $copy->enrollments()->count());
    }

    public function test_course_version_restore_recovers_metadata_and_creates_safety_snapshot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['title' => 'العنوان الأصلي']);

        $this->actingAs($admin)->post(route('admin.courses.versions.store', $course), ['label' => 'الإصدار الأصلي'])->assertRedirect();
        $version = $course->versions()->firstOrFail();
        $course->update(['title' => 'عنوان معدل']);
        $this->post(route('admin.courses.versions.restore', [$course, $version]))->assertRedirect();

        $this->assertSame('العنوان الأصلي', $course->fresh()->title);
        $this->assertNotNull($version->fresh()->restored_at);
        $this->assertDatabaseHas('course_versions', ['course_id' => $course->id, 'event' => 'before_restore']);
    }

    public function test_due_scheduled_course_action_is_executed_by_scheduler(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['is_published' => true]);
        $scheduled = ScheduledCourseAction::create(['course_id' => $course->id, 'created_by' => $admin->id, 'action' => 'unpublish', 'run_at' => now()->subMinute(), 'status' => 'pending']);

        $this->artisan('courses:run-scheduled-actions')->assertSuccessful();

        $this->assertFalse($course->fresh()->is_published);
        $this->assertSame('completed', $scheduled->fresh()->status);
        $this->assertNotNull($scheduled->fresh()->completed_at);
    }

    public function test_risk_scan_detects_inactive_enrolled_student_and_does_not_duplicate_case(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create();
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'enrolled_at' => now()->subMonth()]);

        $this->artisan('students:scan-risk')->assertSuccessful();
        $this->artisan('students:scan-risk')->assertSuccessful();

        $case = StudentSupportCase::where('student_id', $student->id)->firstOrFail();
        $this->assertSame(45, $case->risk_score);
        $this->assertSame('medium', $case->risk_level);
        $this->assertDatabaseCount('student_support_cases', 1);
        $this->assertSame('inactivity', $case->risk_signals[0]['key']);
    }

    public function test_support_agent_can_assign_case_create_task_and_complete_it(): void
    {
        $agent = User::factory()->create(['role' => 'admin', 'admin_role' => 'support_agent']);
        $student = User::factory()->create(['role' => 'student']);
        $case = StudentSupportCase::create(['student_id' => $student->id, 'status' => 'open', 'risk_level' => 'high', 'risk_score' => 85, 'risk_signals' => [], 'detected_at' => now()]);

        $this->actingAs($agent)->put(route('admin.support.update', $case), ['status' => 'monitoring', 'assigned_to' => $agent->id, 'next_follow_up_at' => now()->addDay()->toDateTimeString()])->assertRedirect();
        $this->post(route('admin.support.activities.store', $case), ['type' => 'task', 'body' => 'الاتصال بالطالب لمراجعة خطة الدراسة.', 'due_at' => now()->addHours(4)->toDateTimeString()])->assertRedirect();
        $task = $case->activities()->firstOrFail();
        $this->post(route('admin.support.activities.complete', $task))->assertRedirect();

        $this->assertSame('monitoring', $case->fresh()->status);
        $this->assertSame($agent->id, $case->fresh()->assigned_to);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->get(route('admin.support.show', $case))->assertOk()->assertSee('الاتصال بالطالب لمراجعة خطة الدراسة.');
    }

    public function test_support_pages_render_legacy_string_risk_signals_safely(): void
    {
        $agent = User::factory()->create(['role' => 'admin', 'admin_role' => 'support_agent']);
        $student = User::factory()->create(['role' => 'student']);
        $case = StudentSupportCase::create([
            'student_id' => $student->id,
            'status' => 'open',
            'risk_level' => 'high',
            'risk_score' => 88,
            'risk_signals' => ['low_progress', 'inactive'],
            'detected_at' => now(),
        ]);

        $this->actingAs($agent)->get(route('admin.support.index'))
            ->assertOk()
            ->assertSee('تقدم تعليمي منخفض')
            ->assertSee('انخفاض النشاط مؤخراً');

        $this->get(route('admin.support.show', $case))
            ->assertOk()
            ->assertSee('تقدم تعليمي منخفض')
            ->assertSee('انخفاض النشاط مؤخراً');
    }

    public function test_parent_escalation_notifies_only_linked_guardians(): void
    {
        $agent = User::factory()->create(['role' => 'admin', 'admin_role' => 'support_agent']);
        $student = User::factory()->create(['role' => 'student']);
        $linkedParent = User::factory()->create(['role' => 'parent']);
        $otherParent = User::factory()->create(['role' => 'parent']);
        $student->linkedParents()->attach($linkedParent->id, ['grade' => 'grade-1', 'linked_at' => now()]);
        $case = StudentSupportCase::create(['student_id' => $student->id, 'status' => 'open', 'risk_level' => 'high', 'risk_score' => 80, 'detected_at' => now()]);

        $this->actingAs($agent)->post(route('admin.support.activities.store', $case), [
            'type' => 'parent_escalation', 'channel' => 'in_app', 'body' => 'يرجى متابعة خطة الدراسة الأسبوعية مع الطالب.',
        ])->assertRedirect();

        $this->assertDatabaseHas('user_notifications', ['user_id' => $linkedParent->id, 'type' => 'student_support']);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $otherParent->id, 'type' => 'student_support']);
        $this->assertDatabaseHas('student_support_activities', ['student_support_case_id' => $case->id, 'type' => 'parent_escalation']);
    }

    public function test_system_console_is_super_admin_only_and_safe_settings_control_registration(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($superAdmin)->get(route('admin.system.index'))->assertOk()->assertSee('النظام والأمان');
        $this->put(route('admin.system.settings.update'), [
            'academy_name_ar' => 'أكاديمية الاختبار', 'academy_name_en' => 'Test Academy',
            'support_email' => 'support@test.example', 'default_locale' => 'ar',
            'maintenance_banner_ar' => '', 'maintenance_banner_en' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('platform_settings', ['key' => 'academy_name_ar']);
        $this->postJson('/api/auth/register', [
            'name' => 'New Student',
            'email' => 'new@test.example',
            'student_phone' => '01012345678',
            'parent_phone' => '01112345678',
            'grade' => 'grade-1',
            'relationship_type' => 'father',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ])->assertForbidden();

        $support = User::factory()->create(['role' => 'admin', 'admin_role' => 'support_agent']);
        $this->actingAs($support)->get(route('admin.system.index'))->assertForbidden();
    }

    public function test_admin_login_attempts_are_recorded_and_audit_can_be_exported(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'password']);
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));

        $this->assertSame(2, LoginEvent::where('email', $admin->email)->count());
        $this->assertDatabaseHas('login_events', ['email' => $admin->email, 'successful' => false]);
        $this->assertDatabaseHas('login_events', ['email' => $admin->email, 'successful' => true]);
        $this->get(route('admin.system.security'))->assertOk()->assertSee($admin->email);
        $this->get(route('admin.system.audit.export'))->assertOk()->assertDownload();
    }

    public function test_super_admin_can_revoke_another_database_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'student']);
        DB::table('sessions')->insert(['id' => 'other-session-id', 'user_id' => $other->id, 'ip_address' => '127.0.0.2', 'user_agent' => 'Test browser', 'payload' => '', 'last_activity' => now()->timestamp]);

        $this->actingAs($admin)->delete(route('admin.system.sessions.destroy', 'other-session-id'))->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-id']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'security.session_revoked']);
    }

    private function completedOrder(User $student, Course $course): Order
    {
        return Order::create([
            'public_id' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'amount' => 500, 'currency' => 'EGP', 'status' => 'completed', 'payment_provider' => 'local',
            'payment_method' => 'card', 'session_token_hash' => hash('sha256', Str::random(64)),
            'provider_payment_id' => 'local_'.Str::random(20), 'paid_at' => now(),
        ]);
    }

    private function coursePayload(): array
    {
        return ['id' => 'blade-course', 'title' => 'دورة Blade', 'title_en' => 'Blade Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'description' => '', 'description_en' => '', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed'];
    }
}
