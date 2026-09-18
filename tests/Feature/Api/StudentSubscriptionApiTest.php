<?php

namespace Tests\Feature\Api;

use App\Models\AcademicTerm;
use App\Models\ManualPaymentSubmission;
use App\Models\Order;
use App\Models\StudentSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\StudentSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentSubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_without_subscription_receives_none_state(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_STUDENT]));

        $this->getJson('/api/me/subscription')->assertOk()
            ->assertJsonPath('data.state', 'none')
            ->assertJsonPath('data.current', null)
            ->assertJsonPath('data.availableActions.canUpgrade', false);
    }

    public function test_active_subscription_returns_authoritative_plan_and_academic_coverage(): void
    {
        [$student, $plan, $term] = $this->fixture();
        StudentSubscription::create([
            'student_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'academic_term_id' => $term->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        Sanctum::actingAs($student);

        $this->getJson('/api/me/subscription')->assertOk()
            ->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.current.plan.id', $plan->id)
            ->assertJsonPath('data.current.semesters.0.code', $term->code)
            ->assertJsonPath('data.availableActions.canViewCourses', true);
    }

    public function test_pending_review_is_derived_only_from_authenticated_students_order(): void
    {
        [$student, $plan] = $this->fixture();
        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->subscriptionOrder($other, $plan, 'needs_information');
        $order = $this->subscriptionOrder($student, $plan, 'submitted');
        ManualPaymentSubmission::create([
            'order_id' => $order->id,
            'user_id' => $student->id,
            'channel' => 'instapay',
            'transaction_id' => 'subscription-review-1',
            'sender_identifier' => '01000000000',
            'transferred_at' => now(),
            'submitted_amount' => 100,
            'receipt_path' => 'payment-receipts/test.jpg',
            'receipt_original_name' => 'test.jpg',
            'receipt_mime' => 'image/jpeg',
            'receipt_size' => 100,
            'receipt_sha256' => str_repeat('a', 64),
            'status' => 'submitted',
        ]);
        Sanctum::actingAs($student);

        $this->getJson('/api/me/subscription')->assertOk()
            ->assertJsonPath('data.state', 'pending_review')
            ->assertJsonPath('data.pendingOrder.id', $order->public_id)
            ->assertJsonCount(1, 'data.history');
    }

    public function test_pending_payment_and_rejected_states_follow_the_students_latest_actionable_order(): void
    {
        [$student, $plan] = $this->fixture();
        Sanctum::actingAs($student);

        $pending = $this->subscriptionOrder($student, $plan, 'pending');
        $this->getJson('/api/me/subscription')->assertOk()
            ->assertJsonPath('data.state', 'pending_payment')
            ->assertJsonPath('data.pendingOrder.id', $pending->public_id);

        $pending->update(['status' => 'cancelled']);
        $rejected = $this->subscriptionOrder($student, $plan, 'needs_information');
        $this->getJson('/api/me/subscription')->assertOk()
            ->assertJsonPath('data.state', 'rejected')
            ->assertJsonPath('data.pendingOrder.id', $rejected->public_id);
    }

    public function test_expired_subscription_does_not_grant_course_access(): void
    {
        [$student, $plan, $term] = $this->fixture();
        StudentSubscription::create([
            'student_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'academic_term_id' => $term->id,
            'status' => 'active',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
        ]);
        Sanctum::actingAs($student);

        $this->getJson('/api/me/subscription')->assertOk()
            ->assertJsonPath('data.state', 'expired')
            ->assertJsonPath('data.current', null)
            ->assertJsonPath('data.availableActions.canViewCourses', false);
    }

    public function test_two_orders_cannot_create_duplicate_active_plan_coverage(): void
    {
        [$student, $plan, $term] = $this->fixture();
        $student->onboardingProfile()->create([
            'grade' => $term->grade->code,
            'semester' => $term->code,
        ]);
        $first = $this->subscriptionOrder($student, $plan, 'completed');
        $second = $this->subscriptionOrder($student, $plan, 'completed');
        $service = app(StudentSubscriptionService::class);

        $service->activateFor($first->load('subscriptionPlan'));
        $service->activateFor($second->load('subscriptionPlan'));

        $this->assertDatabaseCount('student_subscriptions', 1);
        $this->assertDatabaseHas('student_subscriptions', [
            'student_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'academic_term_id' => $term->id,
            'order_id' => $first->id,
        ]);
    }

    public function test_parent_cannot_use_student_subscription_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PARENT]));
        $this->getJson('/api/me/subscription')->assertForbidden();
    }

    private function fixture(): array
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $plan = SubscriptionPlan::firstOrFail();
        $term = AcademicTerm::with(['academicYear', 'grade'])->firstOrFail();

        return [$student, $plan, $term];
    }

    private function subscriptionOrder(User $student, SubscriptionPlan $plan, string $status): Order
    {
        return Order::create([
            'public_id' => fake()->uuid(),
            'user_id' => $student->id,
            'beneficiary_user_id' => $student->id,
            'product_type' => 'subscription',
            'subscription_plan_id' => $plan->id,
            'original_amount' => 100,
            'discount_amount' => 0,
            'amount' => 100,
            'currency' => 'EGP',
            'status' => $status,
            'assignment_status' => 'pending',
            'payment_provider' => 'manual_transfer',
            'payment_method' => 'instapay',
            'session_token_hash' => hash('sha256', fake()->uuid()),
        ]);
    }
}
