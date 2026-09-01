<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\GuardianInvitation;
use App\Models\Order;
use App\Models\ParentStudentLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_policy_centralizes_report_and_purchase_boundaries(): void
    {
        $parent = User::factory()->create(['role' => User::ROLE_PARENT]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $link = ParentStudentLink::create([
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'status' => 'accepted',
            'linked_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->assertTrue(Gate::forUser($parent)->allows('viewLearningReport', $student));
        $this->assertTrue(Gate::forUser($parent)->allows('purchaseFor', $student));

        $link->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->assertFalse(Gate::forUser($parent)->allows('viewLearningReport', $student));
        $this->assertFalse(Gate::forUser($parent)->allows('purchaseFor', $student));
    }

    public function test_family_policies_conceal_resources_from_non_participants(): void
    {
        $parent = User::factory()->withEgyptianPhone('+201112345678')->create(['role' => User::ROLE_PARENT]);
        $outsider = User::factory()->withEgyptianPhone('+201155555555')->create(['role' => User::ROLE_PARENT]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $link = ParentStudentLink::create([
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'status' => 'accepted',
            'linked_at' => now(),
            'accepted_at' => now(),
        ]);
        $invitation = GuardianInvitation::create([
            'student_id' => $student->id,
            'parent_user_id' => $parent->id,
            'parent_phone_e164' => $parent->phone_e164,
            'relationship_type' => 'father',
            'token_hash' => hash('sha256', 'policy-token'),
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $this->assertTrue(Gate::forUser($parent)->allows('revoke', $link));
        $this->assertFalse(Gate::forUser($outsider)->allows('revoke', $link));
        $this->assertTrue(Gate::forUser($parent)->allows('respond', $invitation));
        $this->assertFalse(Gate::forUser($outsider)->allows('respond', $invitation));
    }

    public function test_order_policy_allows_only_owner_or_authorized_finance_staff(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $outsider = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $finance = User::factory()->create(['role' => User::ROLE_ADMIN, 'admin_role' => 'finance_manager']);
        $course = Course::factory()->create();
        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'beneficiary_user_id' => $owner->id,
            'course_id' => $course->id,
            'amount' => 100,
            'currency' => 'EGP',
            'status' => 'pending',
            'payment_provider' => 'manual_transfer',
            'payment_method' => 'instapay',
            'session_token_hash' => hash('sha256', 'policy-order-token'),
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $order));
        $this->assertFalse(Gate::forUser($outsider)->allows('view', $order));
        $this->assertTrue(Gate::forUser($finance)->allows('view', $order));
    }
}
