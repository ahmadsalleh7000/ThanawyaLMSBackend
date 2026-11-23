<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\ManualPaymentSubmission;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ManualPaymentReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->create(['role' => 'student', 'password' => 'AuditPassword123!']);
    }

    private function course(): Course
    {
        return Course::factory()->create(['scope' => 'general', 'price' => 0]);
    }

    private function exam(Course $course): Assessment
    {
        $exam = $course->assessment()->create(['duration_minutes' => 20, 'passing_score' => 70, 'is_published' => true, 'max_retries' => 0]);
        $exam->questions()->create(['position' => 1, 'type' => 'multiple_choice', 'topic' => 'Loops', 'topic_ar' => 'التكرار', 'prompt' => 'Choose A', 'prompt_ar' => 'اختر أ', 'options' => ['A', 'B'], 'options_ar' => ['أ', 'ب'], 'correct_option' => 0, 'explanation' => 'A is correct', 'explanation_ar' => 'أ صحيحة', 'is_active' => true]);

        return $exam;
    }

    public function test_passing_before_completing_lessons_issues_one_certificate_without_a_retake(): void
    {
        $student = $this->student();
        $course = $this->course();
        $lesson = Lesson::factory()->create(['course_id' => $course->id, 'status' => 'published']);
        $exam = $this->exam($course);
        Sanctum::actingAs($student);
        $draft = $this->postJson("/api/courses/{$course->id}/assessment/start")->assertOk()->json('data');
        $this->postJson("/api/courses/{$course->id}/assessment/submit", ['draft_id' => $draft['id'], 'answers' => [$exam->questions->first()->id => 0]])->assertOk()->assertJsonPath('passed', true)->assertJsonPath('certificateId', null);
        $this->postJson("/api/courses/{$course->id}/lessons/{$lesson->slug}/complete")->assertOk();
        $this->postJson("/api/courses/{$course->id}/lessons/{$lesson->slug}/complete")->assertOk();
        $this->getJson("/api/courses/{$course->id}/certificate-status")->assertOk()->assertJsonPath('data.assessmentPassed', true);
        $this->getJson("/api/courses/{$course->id}/certificate")->assertOk();
        $this->assertSame(1, $student->certificates()->count());
        $this->assertSame(1, $exam->attempts()->count());
    }

    public function test_preview_media_cannot_bypass_draft_or_future_release_rules(): void
    {
        Storage::fake('local');
        $course = $this->course();
        $lesson = Lesson::factory()->create(['course_id' => $course->id, 'status' => 'published', 'is_preview' => true]);
        $media = $lesson->mediaAssets()->create(['kind' => 'resource', 'status' => 'draft', 'disk' => 'local', 'path' => 'audit.pdf', 'original_name' => 'audit.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10]);
        Sanctum::actingAs($this->student());
        $this->getJson("/api/media/{$media->id}/access")->assertNotFound();
        $media->update(['status' => 'published', 'available_from' => now()->addDay()]);
        $this->getJson("/api/media/{$media->id}/access")->assertNotFound();
        $media->update(['available_from' => null]);
        $this->getJson("/api/media/{$media->id}/access")->assertOk();
    }

    public function test_notifications_paginate_and_count_unread_beyond_the_first_page(): void
    {
        $student = $this->student();
        for ($i = 0; $i < 55; $i++) {
            $student->userNotifications()->create(['type' => 'course', 'title' => 'Synthetic notice', 'message' => 'Synthetic content', 'title_ar' => 'إشعار اصطناعي', 'message_ar' => 'محتوى اصطناعي']);
        }
        Sanctum::actingAs($student);
        $this->getJson('/api/me/notifications')->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.unreadCount', 55)->assertJsonPath('meta.lastPage', 3);
        $this->getJson('/api/me/notifications?page=3')->assertOk()->assertJsonCount(15, 'data');
        $this->postJson('/api/me/notifications/read-all')->assertOk();
        $this->getJson('/api/me/notifications?filter=unread')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.unreadCount', 0)->assertJsonPath('meta.readCount', 55);
    }

    public function test_rejected_students_can_seek_support_but_cannot_learn_or_create_new_purchases(): void
    {
        $student = $this->student();
        $student->update(['is_active' => false, 'registration_status' => 'rejected']);
        Sanctum::actingAs($student);
        $this->getJson('/api/support/tickets')->assertOk();
        $this->postJson('/api/support/tickets', ['subject' => 'Payment review help', 'category' => 'payment', 'priority' => 'normal', 'message' => 'Please help me correct this synthetic payment.'])->assertCreated();
        $this->getJson('/api/me/learning')->assertForbidden();
        $this->postJson('/api/me/subscription/checkout', [])->assertForbidden();
    }

    public function test_personal_goals_persist_and_dashboard_reports_recorded_study_activity(): void
    {
        $student = $this->student();
        $lesson = Lesson::factory()->create(['course_id' => $this->course()->id, 'status' => 'published', 'duration_minutes' => 20]);
        $student->lessonCompletions()->create(['lesson_id' => $lesson->id, 'completed_at' => now()]);
        Sanctum::actingAs($student);
        $goal = ['id' => 'own-goal', 'title' => '', 'category' => 'lessons', 'current' => 1, 'target' => 5, 'deadline' => now()->addWeek()->toDateString()];
        $this->putJson('/api/student/goals', ['goals' => [$goal]])->assertOk();
        $this->getJson('/api/me/learning-summary')->assertOk()->assertJsonPath('data.goals.0.id', 'own-goal')->assertJsonPath('data.currentStreak', 1)->assertJsonPath('data.studyMinutes', 20)->assertJsonCount(7, 'data.weeklyActivity');
        Sanctum::actingAs($this->student());
        $this->getJson('/api/me/learning-summary')->assertOk()->assertJsonPath('data.goals', []);
    }

    public function test_historical_review_uses_owned_snapshots_and_respects_release_policy(): void
    {
        $student = $this->student();
        $exam = $this->exam($this->course());
        Sanctum::actingAs($student);
        $draft = $this->postJson("/api/courses/{$exam->course_id}/assessment/start")->json('data');
        $attemptId = $this->postJson("/api/courses/{$exam->course_id}/assessment/submit", ['draft_id' => $draft['id'], 'answers' => [$exam->questions->first()->id => 1]])->assertOk()->json('attemptId');
        $exam->questions->first()->update(['prompt' => 'Changed after the exam', 'correct_option' => 1]);
        $url = "/api/me/assessment-review/exam/{$exam->id}/{$attemptId}";
        $this->getJson($url)->assertOk()->assertJsonPath('data.questions.0.prompt', 'Choose A')->assertJsonPath('data.result.review.0.correctOption', 0);
        $this->getJson('/api/me/learning-results')->assertOk()->assertJsonPath('data.0.url', "/assessment-review/exam/{$exam->id}/{$attemptId}");
        $this->getJson('/api/me/recommendations/weak-topics')->assertOk()->assertJsonPath('data.0.incorrect', 1);
        $exam->update(['show_correct_answers' => false]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.questions', [])->assertJsonPath('data.result.review', []);
        Sanctum::actingAs($this->student());
        $this->getJson($url)->assertNotFound();
    }

    public function test_catalog_summaries_keep_scheduled_metadata_and_omit_lesson_bodies(): void
    {
        $course = $this->course();
        Lesson::factory()->create(['course_id' => $course->id, 'status' => 'published', 'available_from' => now()->addDay(), 'video_url' => 'https://example.invalid/private.mp4']);
        Sanctum::actingAs($this->student());
        $payload = $this->getJson('/api/courses?view=catalog')->assertOk()->json('data.0.lessons.0');
        $this->assertTrue($payload['locked']);
        $this->assertSame('scheduled', $payload['lockReason']);
        $this->assertArrayNotHasKey('videoUrl', $payload);
        $this->assertArrayNotHasKey('sections', $payload);
    }

    public function test_renewal_quote_uses_server_price_and_activates_only_after_finance_approval(): void
    {
        Storage::fake('local');
        config(['payments.provider' => 'manual_transfer', 'payments.manual_transfer.channels.instapay.destination' => 'synthetic@instapay']);
        $student = $this->student();
        $plan = SubscriptionPlan::where('is_active', true)->where('billing_period', 'semester')->firstOrFail();
        $term = $plan->academicTerms()->firstOrFail();
        $term->update(['ends_on' => now()->addMonths(2)->toDateString(), 'starts_on' => now()->subWeek()->toDateString()]);
        Sanctum::actingAs($student);
        $payload = ['subscription_plan_id' => $plan->id, 'academic_term_id' => $term->id, 'payment_method' => 'instapay', 'amount' => 1];
        $session = $this->postJson('/api/me/subscription/checkout', $payload)->assertCreated()->json();
        $this->assertEquals((float) $plan->price, $session['amount']);
        $again = $this->postJson('/api/me/subscription/checkout', $payload)->assertCreated()->json();
        $this->assertSame($session['order_id'], $again['order_id']);
        $this->assertSame(0, $student->studentSubscriptions()->active()->count());
        $submission = $this->post('/api/payments/manual/submit', ['session_id' => $again['session_id'], 'channel' => 'instapay', 'transaction_id' => 'SYNTHETIC-RENEWAL', 'sender_identifier' => '01012345678', 'transferred_at' => now()->subMinute()->toISOString(), 'submitted_amount' => $session['amount'], 'receipt' => UploadedFile::fake()->image('synthetic.png')], ['Accept' => 'application/json'])->assertCreated()->json();
        app(ManualPaymentReviewService::class)->approve(ManualPaymentSubmission::findOrFail($submission['id']), User::factory()->create(['role' => 'admin']), 'Synthetic verification');
        $this->assertSame(1, $student->studentSubscriptions()->active()->where('academic_term_id', $term->id)->count());
        $this->postJson('/api/me/subscription/checkout', $payload)->assertConflict();
    }

    public function test_arabic_login_error_and_other_session_revocation(): void
    {
        $this->withHeader('Accept-Language', 'ar')->postJson('/api/auth/login', ['email' => 'missing@test.local', 'password' => 'AuditPassword123!'])->assertUnprocessable()->assertJsonPath('errors.email.0', 'بيانات الدخول غير صحيحة. راجع البريد أو رقم الهاتف وكلمة المرور.');
        $student = $this->student();
        $one = $student->createToken('current');
        $two = $student->createToken('other');
        $this->withToken($one->plainTextToken)->putJson('/api/auth/password', ['current_password' => 'AuditPassword123!', 'password' => 'NewAuditPassword123!', 'password_confirmation' => 'NewAuditPassword123!'])->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $one->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $two->accessToken->id]);
    }

    public function test_offline_reading_requires_explicit_public_pdf_permission(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('reading.pdf', '%PDF-1.4 synthetic reading');
        $lesson = Lesson::factory()->create(['course_id' => $this->course()->id, 'status' => 'published', 'is_preview' => true, 'allow_pdf_download' => true]);
        $media = $lesson->mediaAssets()->create(['kind' => 'resource', 'status' => 'published', 'disk' => 'local', 'path' => 'reading.pdf', 'original_name' => 'reading.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 26]);
        $this->get("/api/media/{$media->id}/offline")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $lesson->update(['allow_pdf_download' => false]);
        $this->get("/api/media/{$media->id}/offline")->assertNotFound();
        $lesson->update(['allow_pdf_download' => true, 'is_preview' => false]);
        $this->get("/api/media/{$media->id}/offline")->assertNotFound();
        $lesson->update(['is_preview' => true, 'available_from' => now()->addDay()]);
        $this->get("/api/media/{$media->id}/offline")->assertNotFound();
    }

    public function test_workspace_recovers_notes_and_visits_and_external_video_has_an_explicit_completion_path(): void
    {
        $student = $this->student();
        $course = $this->course();
        $lesson = Lesson::factory()->create(['course_id' => $course->id, 'status' => 'published', 'video_url' => 'https://drive.google.com/file/d/synthetic/preview', 'completion_rules' => ['minimum_video_seconds' => 60]]);
        Sanctum::actingAs($student);
        $root = "/api/courses/{$course->id}/lessons/{$lesson->slug}";
        $this->postJson("{$root}/complete")->assertUnprocessable();
        $this->patchJson("{$root}/workspace", ['visited' => true, 'note' => 'My recovered note', 'video_confirmed' => true])->assertOk()->assertJsonPath('data.note', 'My recovered note')->assertJsonPath('data.videoConfirmed', true);
        $workspace = $this->getJson('/api/me/learning')->assertOk()->json('lessonWorkspace');
        $this->assertNotEmpty($workspace[$course->id.'.'.$lesson->slug]['lastOpenedAt']);
        $this->assertSame('My recovered note', $workspace[$course->id.'.'.$lesson->slug]['note']);
        $this->postJson("{$root}/complete")->assertOk();
        $lesson->update(['video_url' => 'https://example.invalid/native.mp4']);
        $this->postJson("{$root}/complete")->assertUnprocessable();
        $this->patchJson("{$root}/workspace", ['playback_seconds' => 60])->assertOk();
        $this->postJson("{$root}/complete")->assertOk();
    }

    public function test_rejecting_renewal_evidence_keeps_the_existing_student_account_and_access(): void
    {
        $student = $this->student();
        $plan = SubscriptionPlan::where('is_active', true)->firstOrFail();
        $order = $student->orders()->create(['public_id' => (string) Str::uuid(), 'product_type' => 'subscription', 'subscription_plan_id' => $plan->id, 'amount' => $plan->price, 'original_amount' => $plan->price, 'discount_amount' => 0, 'currency' => 'EGP', 'status' => 'submitted', 'session_token_hash' => hash('sha256', 'synthetic'), 'payment_provider' => 'manual_transfer', 'payment_method' => 'instapay', 'provider_payload' => ['source' => 'subscription_renewal']]);
        $submission = $order->manualPaymentSubmissions()->create(['user_id' => $student->id, 'channel' => 'instapay', 'transaction_id' => 'SYNTHETIC-REJECT', 'sender_identifier' => '01012345678', 'transferred_at' => now(), 'submitted_amount' => $order->amount, 'receipt_disk' => 'local', 'receipt_path' => 'synthetic.png', 'receipt_original_name' => 'synthetic.png', 'receipt_mime' => 'image/png', 'receipt_size' => 10, 'receipt_sha256' => str_repeat('a', 64), 'status' => 'submitted']);
        app(ManualPaymentReviewService::class)->decide($submission, User::factory()->create(['role' => 'admin']), 'rejected', 'Synthetic evidence could not be verified.');
        $this->assertTrue($student->fresh()->isEligibleForAccess());
        $this->assertSame('approved', $student->fresh()->registration_status->value ?? $student->fresh()->registration_status);
        $this->assertSame('rejected', $order->fresh()->status);
    }

    public function test_syllabus_traversal_follows_unit_order_even_when_lesson_positions_restart_per_unit(): void
    {
        $course = $this->course();
        $firstUnit = $course->units()->create(['slug' => 'first-unit', 'title_ar' => 'الوحدة الأولى', 'title_en' => 'First unit', 'position' => 1, 'status' => 'published']);
        $secondUnit = $course->units()->create(['slug' => 'second-unit', 'title_ar' => 'الوحدة الثانية', 'title_en' => 'Second unit', 'position' => 2, 'status' => 'published']);
        $second = Lesson::factory()->create(['course_id' => $course->id, 'unit_id' => $secondUnit->id, 'position' => 1, 'status' => 'published']);
        $first = Lesson::factory()->create(['course_id' => $course->id, 'unit_id' => $firstUnit->id, 'position' => 2, 'status' => 'published']);
        Sanctum::actingAs($this->student());
        $this->getJson('/api/courses?view=catalog')->assertOk()->assertJsonPath('data.0.lessons.0.id', $first->slug)->assertJsonPath('data.0.lessons.1.id', $second->slug);
        $this->getJson("/api/courses/{$course->id}")->assertOk()->assertJsonPath('data.lessons.0.unitTitleEn', 'First unit')->assertJsonPath('data.lessons.1.unitTitleEn', 'Second unit');
    }
}
