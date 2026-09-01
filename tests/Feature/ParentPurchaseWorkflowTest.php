<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\ManualPaymentSubmission;
use App\Models\ParentStudentLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParentPurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.provider' => 'manual_transfer', 'payments.manual_transfer.channels.instapay.destination' => 'academy@instapay']);
        Storage::fake('local');
    }

    public function test_parent_payment_assigns_course_only_after_admin_approval(): void
    {
        $parent = User::factory()->create(['role' => User::ROLE_PARENT]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        ParentStudentLink::create(['parent_id' => $parent->id, 'student_id' => $student->id, 'status' => 'accepted', 'accepted_at' => now(), 'linked_at' => now()]);
        $course = $this->course();
        Sanctum::actingAs($parent);

        $session = $this->postJson('/api/payments/checkout', ['course_id' => $course->id, 'student_id' => $student->id, 'payment_method' => 'instapay'])
            ->assertCreated()->assertJsonPath('beneficiary.id', $student->id)->json('session_id');
        $order = $parent->orders()->firstOrFail();
        $this->assertSame($student->id, $order->beneficiary_user_id);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);

        $this->post('/api/payments/manual/submit', [
            'session_id' => $session, 'channel' => 'instapay', 'transaction_id' => 'PARENT-2026-1',
            'sender_identifier' => 'parent@instapay', 'transferred_at' => now()->toDateTimeString(),
            'submitted_amount' => 350, 'receipt' => UploadedFile::fake()->image('parent-receipt.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);

        $submission = ManualPaymentSubmission::firstOrFail();
        $finance = User::factory()->create(['role' => User::ROLE_ADMIN, 'admin_role' => 'finance_manager']);
        $this->actingAs($finance)->post(route('admin.payment-reviews.review', $submission), ['decision' => 'approved'])->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'completed', 'assignment_status' => 'assigned']);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $parent->id, 'course_id' => $course->id]);

        Sanctum::actingAs($parent);
        $this->getJson('/api/me/orders')->assertOk()
            ->assertJsonPath('data.0.beneficiary.id', $student->id)
            ->assertJsonPath('data.0.assignmentStatus', 'assigned')
            ->assertJsonPath('data.0.isGiftPurchase', true);
    }

    public function test_parent_cannot_purchase_for_an_unapproved_or_unrelated_student(): void
    {
        $parent = User::factory()->create(['role' => User::ROLE_PARENT]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        ParentStudentLink::create(['parent_id' => $parent->id, 'student_id' => $student->id, 'status' => 'pending', 'linked_at' => now()]);
        Sanctum::actingAs($parent);

        $this->postJson('/api/payments/checkout', ['course_id' => $this->course()->id, 'student_id' => $student->id, 'payment_method' => 'instapay'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    private function course(): Course
    {
        return Course::create(['id' => 'parent-course', 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 350, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
    }
}
