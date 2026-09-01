<?php

namespace Tests\Feature;

use App\Models\AccountDeletionRequest;
use App\Models\Course;
use App\Models\GuardianInvitation;
use App\Models\Invoice;
use App\Models\ManualPaymentSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_can_export_a_redacted_portable_data_file(): void
    {
        $user = User::factory()->withEgyptianPhone('+201012345678')->create(['password' => 'strong-password']);

        $response = $this->actingAs($user, 'sanctum')->get('/api/auth/privacy/export');

        $response->assertOk()->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString($user->email, $content);
        $this->assertStringContainsString('+201012345678', $content);
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('session_token_hash', $content);
    }

    public function test_deletion_request_requires_password_and_can_be_cancelled_during_cooling_off(): void
    {
        $user = User::factory()->create(['password' => 'strong-password']);

        $this->actingAs($user, 'sanctum')->postJson('/api/auth/privacy/deletion', [
            'current_password' => 'wrong-password',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/privacy/deletion', [
            'current_password' => 'strong-password',
            'reason' => 'I no longer need the account.',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('account_deletion_requests', ['user_id' => $user->id, 'status' => 'pending']);
        $this->deleteJson('/api/auth/privacy/deletion')->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_administrator_cannot_use_learner_account_deletion_flow(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/auth/privacy/deletion')->assertForbidden();
    }

    public function test_admin_approves_then_scheduler_securely_anonymizes_and_later_prunes_legal_records(): void
    {
        Storage::fake('local');
        $student = User::factory()->withEgyptianPhone('+201012345678')->create(['role' => 'student', 'name' => 'Private Student', 'email' => 'private@example.com', 'password' => 'strong-password']);
        GuardianInvitation::create([
            'student_id' => $student->id,
            'parent_phone_e164' => '+201112345678',
            'relationship_type' => 'guardian',
            'token_hash' => hash('sha256', 'privacy-invitation'),
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);
        $course = Course::factory()->create(['is_published' => true]);
        $lesson = $course->lessons()->create(['slug' => 'privacy-lesson', 'title' => 'درس', 'title_en' => 'Lesson', 'duration_minutes' => 10, 'position' => 1]);
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now(), 'status' => 'active']);
        $student->lessonCompletions()->create(['lesson_id' => $lesson->id, 'completed_at' => now()]);
        $student->courseReviews()->create(['course_id' => $course->id, 'rating' => 5, 'comment' => 'Personal learning feedback.', 'status' => 'published']);
        $order = $student->orders()->create(['public_id' => fake()->uuid(), 'beneficiary_user_id' => $student->id, 'course_id' => $course->id, 'product_type' => 'course', 'original_amount' => 350, 'discount_amount' => 0, 'amount' => 350, 'currency' => 'EGP', 'status' => 'completed', 'assignment_status' => 'assigned', 'payment_provider' => 'manual_transfer', 'payment_method' => 'instapay', 'session_token_hash' => hash('sha256', 'privacy-session'), 'paid_at' => now()]);
        Invoice::create(['number' => 'INV-PRIVACY-1', 'order_id' => $order->id, 'buyer_name' => $student->name, 'buyer_email' => $student->email, 'line_items' => [['name' => 'Course']], 'subtotal' => 350, 'discount' => 0, 'total' => 350, 'currency' => 'EGP', 'issued_at' => now()]);
        Storage::disk('local')->put('payment-receipts/privacy.jpg', 'receipt');
        ManualPaymentSubmission::create(['order_id' => $order->id, 'user_id' => $student->id, 'channel' => 'instapay', 'transaction_id' => 'PRIVACY-REF', 'sender_identifier' => 'private@instapay', 'transferred_at' => now(), 'submitted_amount' => 350, 'receipt_path' => 'payment-receipts/privacy.jpg', 'receipt_original_name' => 'privacy.jpg', 'receipt_mime' => 'image/jpeg', 'receipt_size' => 7, 'receipt_sha256' => hash('sha256', 'receipt'), 'status' => 'approved']);
        $deletion = AccountDeletionRequest::create(['user_id' => $student->id, 'status' => 'pending', 'reason' => 'Delete my account', 'scheduled_for' => now()->subMinute(), 'policy_version' => config('privacy.policy_version')]);
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'super_admin']);

        $this->actingAs($admin)->post(route('admin.privacy.review', $deletion), ['decision' => 'approved', 'review_note' => 'Identity and financial obligations checked.'])->assertRedirect();
        $this->artisan('privacy:process-deletions')->assertSuccessful();

        $student->refresh();
        $deletion->refresh();
        $this->assertFalse($student->is_active);
        $this->assertNotNull($student->anonymized_at);
        $this->assertStringContainsString('@privacy.invalid', $student->email);
        $this->assertNull($student->phone_e164);
        $this->assertNull($student->phone_verified_at);
        $this->assertSame('completed', $deletion->status);
        $this->assertNotNull($deletion->anonymization_reference);
        $this->assertDatabaseMissing('lesson_completions', ['user_id' => $student->id]);
        $this->assertDatabaseMissing('course_reviews', ['user_id' => $student->id]);
        $this->assertDatabaseMissing('guardian_invitations', ['student_id' => $student->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('invoices', ['order_id' => $order->id]);
        Storage::disk('local')->assertMissing('payment-receipts/privacy.jpg');

        $deletion->update(['legal_records_until' => now()->subMinute()]);
        $this->artisan('privacy:prune-legal-records')->assertSuccessful();
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('invoices', ['order_id' => $order->id]);
        $this->assertNotNull($deletion->refresh()->legal_records_pruned_at);
    }

    public function test_admin_can_reject_a_deletion_request_with_visible_reason(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $deletion = AccountDeletionRequest::create(['user_id' => $student->id, 'status' => 'pending', 'scheduled_for' => now()->addDays(30)]);
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'super_admin']);

        $this->actingAs($admin)->post(route('admin.privacy.review', $deletion), ['decision' => 'rejected', 'review_note' => 'An unresolved payment dispute requires follow-up.'])->assertRedirect();
        $this->actingAs($student, 'sanctum')->getJson('/api/auth/privacy/deletion')->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.reviewNote', 'An unresolved payment dispute requires follow-up.')
            ->assertJsonPath('data.retention.financialRecordYears', 7);
    }
}
