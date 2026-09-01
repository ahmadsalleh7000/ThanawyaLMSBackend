<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\ManualPaymentSubmission;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualPaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payments.provider' => 'manual_transfer',
            'payments.manual_transfer.channels.instapay.destination' => 'academy@instapay',
        ]);
        Storage::fake('local');
    }

    public function test_student_submits_receipt_and_finance_admin_approves_enrollment(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course();
        Sanctum::actingAs($student);
        $session = $this->postJson('/api/payments/checkout', ['course_id' => $course->id, 'payment_method' => 'instapay'])
            ->assertCreated()->assertJsonPath('payment.destination', 'academy@instapay')->json('session_id');

        $this->post('/api/payments/manual/submit', [
            'session_id' => $session,
            'channel' => 'instapay',
            'transaction_id' => 'IPN-2026-10001',
            'sender_identifier' => 'ahmed@instapay',
            'transferred_at' => now()->subMinute()->toDateTimeString(),
            'submitted_amount' => 350,
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('status', 'submitted')
            ->assertJsonPath('orderId', $student->orders()->firstOrFail()->public_id)
            ->assertJsonStructure(['submittedAt']);

        $submission = ManualPaymentSubmission::firstOrFail();
        Storage::disk('local')->assertExists($submission->receipt_path);
        $this->assertDatabaseHas('orders', ['id' => $submission->order_id, 'status' => 'submitted']);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);

        $finance = User::factory()->create(['role' => 'admin', 'admin_role' => 'finance_manager']);
        $this->actingAs($finance)->post(route('admin.payment-reviews.review', $submission), ['decision' => 'approved'])
            ->assertRedirect(route('admin.payment-reviews.show', $submission));
        $this->assertDatabaseHas('manual_payment_submissions', ['id' => $submission->id, 'status' => 'approved', 'reviewed_by' => $finance->id]);
        $this->assertDatabaseHas('orders', ['id' => $submission->order_id, 'status' => 'completed', 'provider_payment_id' => 'IPN-2026-10001']);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
        $this->assertDatabaseHas('invoices', ['order_id' => $submission->order_id, 'total' => 350]);
        Sanctum::actingAs($student);
        $this->get('/api/me/orders/'.$submission->order->public_id.'/invoice')->assertOk()->assertHeader('content-type', 'text/html; charset=UTF-8');
    }

    public function test_student_can_resubmit_after_admin_requests_information(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course();
        Sanctum::actingAs($student);
        $session = $this->postJson('/api/payments/checkout', ['course_id' => $course->id, 'payment_method' => 'instapay'])->json('session_id');
        $this->post('/api/payments/manual/submit', ['session_id' => $session, 'channel' => 'instapay', 'transaction_id' => 'OLD-REF', 'sender_identifier' => '01000000000', 'transferred_at' => now()->toDateTimeString(), 'submitted_amount' => 350, 'receipt' => UploadedFile::fake()->image('old.jpg')], ['Accept' => 'application/json'])->assertCreated();
        $first = ManualPaymentSubmission::firstOrFail();
        $finance = User::factory()->create(['role' => 'admin', 'admin_role' => 'finance_manager']);
        $this->actingAs($finance)->post(route('admin.payment-reviews.review', $first), ['decision' => 'needs_information', 'review_note' => 'Please upload a clearer receipt.'])->assertRedirect();

        Sanctum::actingAs($student);
        $this->getJson('/api/me/orders')->assertOk()
            ->assertJsonPath('data.0.resubmissionAllowed', true)
            ->assertJsonPath('data.0.reviewNote', 'Please upload a clearer receipt.')
            ->assertJsonPath('data.0.supportUrl', '/support?new=1&category=payment&order='.$first->order->public_id);
        $this->post('/api/payments/manual/submit', ['order_id' => $first->order->public_id, 'channel' => 'instapay', 'transaction_id' => 'NEW-REF', 'sender_identifier' => '01000000000', 'transferred_at' => now()->toDateTimeString(), 'submitted_amount' => 350, 'receipt' => UploadedFile::fake()->image('new.jpg')], ['Accept' => 'application/json'])->assertCreated();
        $this->assertDatabaseCount('manual_payment_submissions', 2);
        $this->assertDatabaseHas('orders', ['id' => $first->order_id, 'status' => 'submitted']);
    }

    public function test_admin_payment_destination_overrides_environment_and_abandoned_orders_expire(): void
    {
        PlatformSetting::create(['key' => 'instapay_destination', 'value' => 'admin@instapay', 'group' => 'general']);
        PlatformSetting::create(['key' => 'manual_payment_expires_minutes', 'value' => 30, 'group' => 'general']);
        $student = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($student);
        $this->postJson('/api/payments/checkout', ['course_id' => $this->course()->id, 'payment_method' => 'instapay'])->assertJsonPath('payment.destination', 'admin@instapay');
        $order = $student->orders()->firstOrFail();
        $order->forceFill(['created_at' => now()->subMinutes(31)])->saveQuietly();

        $this->artisan('payments:expire-manual-orders')->assertSuccessful();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'expired']);
    }

    public function test_wrong_amount_and_duplicate_transaction_reference_are_rejected(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course();
        Sanctum::actingAs($student);
        $session = $this->postJson('/api/payments/checkout', ['course_id' => $course->id, 'payment_method' => 'instapay'])->json('session_id');
        $payload = ['session_id' => $session, 'channel' => 'instapay', 'transaction_id' => 'DUPLICATE-1', 'sender_identifier' => '01000000000', 'transferred_at' => now()->toDateTimeString(), 'submitted_amount' => 349, 'receipt' => UploadedFile::fake()->image('receipt.jpg')];
        $this->post('/api/payments/manual/submit', $payload, ['Accept' => 'application/json'])->assertUnprocessable();

        $payload['submitted_amount'] = 350;
        $payload['receipt'] = UploadedFile::fake()->image('receipt.jpg');
        $this->post('/api/payments/manual/submit', $payload, ['Accept' => 'application/json'])->assertCreated();
        $other = User::factory()->create(['role' => 'student']);
        $otherCourse = $this->course('manual-course-two');
        Sanctum::actingAs($other);
        $otherSession = $this->postJson('/api/payments/checkout', ['course_id' => $otherCourse->id, 'payment_method' => 'instapay'])->json('session_id');
        $payload['session_id'] = $otherSession;
        $payload['receipt'] = UploadedFile::fake()->image('receipt.jpg');
        $this->post('/api/payments/manual/submit', $payload, ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_non_finance_admin_cannot_read_private_receipt(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $order = $student->orders()->create(['public_id' => fake()->uuid(), 'course_id' => $this->course()->id, 'amount' => 350, 'original_amount' => 350, 'currency' => 'EGP', 'status' => 'submitted', 'payment_provider' => 'manual_transfer', 'payment_method' => 'instapay', 'session_token_hash' => hash('sha256', str_repeat('a', 64))]);
        $submission = ManualPaymentSubmission::create(['order_id' => $order->id, 'user_id' => $student->id, 'channel' => 'instapay', 'transaction_id' => 'PRIVATE-1', 'sender_identifier' => '01000000000', 'transferred_at' => now(), 'submitted_amount' => 350, 'receipt_path' => 'payment-receipts/test.jpg', 'receipt_original_name' => 'test.jpg', 'receipt_mime' => 'image/jpeg', 'receipt_size' => 10, 'receipt_sha256' => str_repeat('a', 64), 'status' => 'submitted']);
        $editor = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor']);

        $this->actingAs($editor)->get(route('admin.payment-reviews.receipt', $submission))->assertForbidden();
    }

    public function test_finance_queue_filters_exports_and_flags_duplicate_receipts(): void
    {
        $course = $this->course();
        $student = User::factory()->create(['role' => 'student', 'name' => 'Searchable Student']);
        $order = $student->orders()->create(['public_id' => fake()->uuid(), 'course_id' => $course->id, 'amount' => 350, 'original_amount' => 350, 'currency' => 'EGP', 'status' => 'submitted', 'payment_provider' => 'manual_transfer', 'payment_method' => 'instapay', 'session_token_hash' => hash('sha256', str_repeat('c', 64))]);
        $attributes = ['order_id' => $order->id, 'user_id' => $student->id, 'channel' => 'instapay', 'transaction_id' => 'FILTER-REF-1', 'sender_identifier' => 'searchable@instapay', 'transferred_at' => now(), 'submitted_amount' => 350, 'receipt_path' => 'payment-receipts/filter.jpg', 'receipt_original_name' => 'filter.jpg', 'receipt_mime' => 'image/jpeg', 'receipt_size' => 10, 'receipt_sha256' => str_repeat('d', 64), 'status' => 'submitted'];
        $first = ManualPaymentSubmission::create($attributes);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $otherOrder = $otherStudent->orders()->create(['public_id' => fake()->uuid(), 'course_id' => $course->id, 'amount' => 350, 'original_amount' => 350, 'currency' => 'EGP', 'status' => 'submitted', 'payment_provider' => 'manual_transfer', 'payment_method' => 'vodafone_cash', 'session_token_hash' => hash('sha256', str_repeat('e', 64))]);
        ManualPaymentSubmission::create(array_merge($attributes, ['order_id' => $otherOrder->id, 'user_id' => $otherStudent->id, 'channel' => 'vodafone_cash', 'transaction_id' => 'OTHER-REF-2']));
        $finance = User::factory()->create(['role' => 'admin', 'admin_role' => 'finance_manager']);

        $this->actingAs($finance)->get(route('admin.payment-reviews.index', ['search' => 'FILTER-REF', 'duplicates' => 1]))
            ->assertOk()->assertSee('FILTER-REF-1')->assertSee('نفس الإيصال مستخدم');
        $this->actingAs($finance)->get(route('admin.payment-reviews.show', $first))->assertOk()->assertSee('إيصال دفع محتمل التكرار')->assertSee('OTHER-REF-2');
        $export = $this->actingAs($finance)->get(route('admin.payment-reviews.export', ['channel' => 'instapay']))->assertOk()->assertDownload();
        $this->assertStringContainsString('FILTER-REF-1', $export->streamedContent());
        $this->assertStringNotContainsString('OTHER-REF-2', $export->streamedContent());
    }

    public function test_payment_reviewer_can_request_information_but_cannot_approve_or_export(): void
    {
        $course = $this->course();
        $student = User::factory()->create(['role' => 'student']);
        $reviewer = User::factory()->create(['role' => 'admin', 'admin_role' => 'finance_reviewer']);

        $makeSubmission = function (string $reference) use ($course, $student): ManualPaymentSubmission {
            $order = $student->orders()->create([
                'public_id' => fake()->uuid(), 'course_id' => $course->id,
                'amount' => 350, 'original_amount' => 350, 'currency' => 'EGP',
                'status' => 'submitted', 'payment_provider' => 'manual_transfer',
                'payment_method' => 'instapay', 'session_token_hash' => hash('sha256', $reference),
            ]);

            return ManualPaymentSubmission::create([
                'order_id' => $order->id, 'user_id' => $student->id, 'channel' => 'instapay',
                'transaction_id' => $reference, 'sender_identifier' => 'reviewer@instapay',
                'transferred_at' => now(), 'submitted_amount' => 350,
                'receipt_path' => "payment-receipts/{$reference}.jpg", 'receipt_original_name' => 'receipt.jpg',
                'receipt_mime' => 'image/jpeg', 'receipt_size' => 10,
                'receipt_sha256' => hash('sha256', "receipt-{$reference}"), 'status' => 'submitted',
            ]);
        };

        $approval = $makeSubmission('REVIEW-APPROVAL');
        $information = $makeSubmission('REVIEW-INFORMATION');

        $this->actingAs($reviewer)
            ->post(route('admin.payment-reviews.review', $approval), ['decision' => 'approved'])
            ->assertForbidden();
        $this->post(route('admin.payment-reviews.review', $information), [
            'decision' => 'needs_information',
            'review_note' => 'يرجى إرسال صورة أوضح للإيصال.',
        ])->assertRedirect();
        $this->get(route('admin.payment-reviews.export'))->assertForbidden();

        $this->assertDatabaseHas('manual_payment_submissions', ['id' => $approval->id, 'status' => 'submitted']);
        $this->assertDatabaseHas('manual_payment_submissions', ['id' => $information->id, 'status' => 'needs_information']);
    }

    public function test_duplicate_receipt_approval_requires_a_documented_override(): void
    {
        $course = $this->course();
        $finance = User::factory()->create(['role' => 'admin', 'admin_role' => 'finance_manager']);
        $student = User::factory()->create(['role' => 'student']);
        $order = $student->orders()->create(['public_id' => fake()->uuid(), 'course_id' => $course->id, 'amount' => 350, 'original_amount' => 350, 'currency' => 'EGP', 'status' => 'submitted', 'payment_provider' => 'manual_transfer', 'payment_method' => 'instapay', 'session_token_hash' => hash('sha256', 'duplicate-order')]);
        $attributes = ['user_id' => $student->id, 'channel' => 'instapay', 'sender_identifier' => 'duplicate@instapay', 'transferred_at' => now(), 'submitted_amount' => 350, 'receipt_path' => 'payment-receipts/duplicate.jpg', 'receipt_original_name' => 'duplicate.jpg', 'receipt_mime' => 'image/jpeg', 'receipt_size' => 10, 'receipt_sha256' => str_repeat('f', 64), 'status' => 'submitted'];
        $submission = ManualPaymentSubmission::create($attributes + ['order_id' => $order->id, 'transaction_id' => 'DUP-APPROVE-1']);
        $otherOrder = $student->orders()->create(['public_id' => fake()->uuid(), 'course_id' => $course->id, 'amount' => 350, 'original_amount' => 350, 'currency' => 'EGP', 'status' => 'submitted', 'payment_provider' => 'manual_transfer', 'payment_method' => 'instapay', 'session_token_hash' => hash('sha256', 'duplicate-other')]);
        ManualPaymentSubmission::create($attributes + ['order_id' => $otherOrder->id, 'transaction_id' => 'DUP-APPROVE-2']);

        $this->actingAs($finance)->post(route('admin.payment-reviews.review', $submission), ['decision' => 'approved'])
            ->assertSessionHasErrors('review_note');
        $this->post(route('admin.payment-reviews.review', $submission), [
            'decision' => 'approved',
            'review_note' => 'تمت مطابقة العملية يدوياً مع كشف الحساب البنكي.',
        ])->assertRedirect();

        $this->assertDatabaseHas('manual_payment_submissions', ['id' => $submission->id, 'status' => 'approved']);
    }

    private function course(string $id = 'manual-course'): Course
    {
        return Course::create(['id' => $id, 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 350, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
    }
}
