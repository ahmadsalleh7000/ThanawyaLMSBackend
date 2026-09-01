<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\AcademicSubject;
use App\Models\AcademicTerm;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\ManualPaymentSubmission;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use Illuminate\Http\UploadedFile;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_registration_stays_pending_without_a_token_until_payment_approval(): void
    {
        PlatformSetting::create(['key' => 'instapay_destination', 'value' => 'payments@instapay', 'group' => 'general']); cache()->forget('platform-settings');
        $response = $this->post('/api/auth/register', [
            'name' => 'Ahmed Student', 'email' => 'student@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123', 'student_phone' => '01012345678',
            'parent_phone' => '01112345678', 'grade' => 'grade-1', 'semester' => 'semester-1', 'relationship_type' => 'father',
            'subscription_plan_id' => SubscriptionPlan::firstOrFail()->id, 'payment_channel' => 'instapay',
            'transaction_id' => 'TX-AUTH-1001', 'sender_identifier' => '01012345678',
            'transferred_at' => now()->subMinute()->format('Y-m-d H:i:s'), 'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('application.status', 'pending_payment_review')->assertJsonMissingPath('token');
        $this->assertDatabaseHas('users', ['email' => 'student@example.com', 'role' => 'student', 'is_active' => false]);
    }

    public function test_full_subscription_promo_code_can_reduce_total_to_zero(): void
    {
        $plan = SubscriptionPlan::firstOrFail();
        Coupon::create([
            'code' => 'FULLFREE',
            'type' => 'percent',
            'value' => 100,
            'minimum_amount' => 0,
            'max_per_user' => 1,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/register/promo-code', [
            'subscription_plan_id' => $plan->id,
            'promo_code' => 'fullfree',
        ])->assertOk();

        $this->assertSame((float) $plan->price, (float) $response->json('data.discount'));
        $this->assertSame(0.0, (float) $response->json('data.total'));
    }

    public function test_full_discount_registration_does_not_require_payment_evidence(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'finance_manager']);
        $plan = SubscriptionPlan::firstOrFail();
        $coupon = Coupon::create([
            'code' => 'FULLPASS',
            'type' => 'percent',
            'value' => 100,
            'minimum_amount' => 0,
            'max_per_user' => 1,
            'is_active' => true,
        ]);
        $coupon->subscriptionPlans()->attach($plan->id);

        $this->postJson('/api/auth/register', [
            'name' => 'Zero Payment Student',
            'email' => 'zero-payment@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'student_phone' => '01012345678',
            'parent_phone' => '01112345678',
            'grade' => 'grade-1',
            'semester' => 'semester-1',
            'relationship_type' => 'father',
            'subscription_plan_id' => $plan->id,
            'promo_code' => 'fullpass',
        ])->assertCreated()->assertJsonPath('application.status', 'approved');

        $student = User::where('email', 'zero-payment@example.com')->firstOrFail();
        $this->assertTrue($student->is_active);
        $this->assertSame('approved', $student->registration_status);
        $this->assertDatabaseHas('orders', [
            'user_id' => $student->id,
            'status' => 'completed',
            'amount' => 0,
            'payment_provider' => 'promo_code',
            'payment_method' => 'promo_code',
        ]);
        $this->assertSame(0, ManualPaymentSubmission::count());
        $this->assertSame(1, Order::where('user_id', $student->id)->firstOrFail()->redemption()->where('status', 'redeemed')->count());
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => 'student_registration',
            'title' => 'Student auto-approved by promo code',
            'action_url' => '/admin/users/'.$student->id,
        ]);
    }

    public function test_registration_semester_scopes_default_student_course_access(): void
    {
        $plan = SubscriptionPlan::firstOrFail();
        $coupon = Coupon::create([
            'code' => 'SEMESTERPASS',
            'type' => 'percent',
            'value' => 100,
            'minimum_amount' => 0,
            'max_per_user' => 1,
            'is_active' => true,
        ]);
        $coupon->subscriptionPlans()->attach($plan->id);

        $selectedTerm = AcademicTerm::active()
            ->whereHas('grade', fn ($query) => $query->where('code', 'grade-2'))
            ->where('code', 'semester-2')
            ->firstOrFail();
        $otherTerm = AcademicTerm::active()
            ->whereHas('grade', fn ($query) => $query->where('code', 'grade-2'))
            ->where('code', 'semester-1')
            ->firstOrFail();
        $included = $this->courseFor($selectedTerm, 'grade-two-semester-two-course');
        $excluded = $this->courseFor($otherTerm, 'grade-two-semester-one-course');

        $this->postJson('/api/auth/register', [
            'name' => 'Second Term Student',
            'email' => 'second-term@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'student_phone' => '01012345681',
            'parent_phone' => '01112345681',
            'grade' => 'grade-2',
            'semester' => 'semester-2',
            'relationship_type' => 'father',
            'subscription_plan_id' => $plan->id,
            'promo_code' => 'semesterpass',
        ])->assertCreated()->assertJsonPath('application.status', 'approved');

        $student = User::where('email', 'second-term@example.com')->firstOrFail();
        $this->assertDatabaseHas('student_onboarding_profiles', [
            'student_id' => $student->id,
            'grade' => 'grade-2',
            'semester' => 'semester-2',
        ]);
        $this->assertDatabaseHas('student_subscriptions', [
            'student_id' => $student->id,
            'academic_term_id' => $selectedTerm->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('student_subscriptions', [
            'student_id' => $student->id,
            'academic_term_id' => $otherTerm->id,
        ]);

        Sanctum::actingAs($student);
        $this->getJson('/api/courses')
            ->assertOk()
            ->assertJsonFragment(['id' => $included->id])
            ->assertJsonMissing(['id' => $excluded->id]);
        $this->getJson('/api/courses/'.$included->id)->assertOk();
        $this->getJson('/api/courses/'.$excluded->id)->assertForbidden();
    }

    public function test_partial_discount_registration_stays_in_manual_payment_review(): void
    {
        PlatformSetting::create(['key' => 'instapay_destination', 'value' => 'payments@instapay', 'group' => 'general']);
        cache()->forget('platform-settings');

        $plan = SubscriptionPlan::firstOrFail();
        $coupon = Coupon::create([
            'code' => 'HALFPASS',
            'type' => 'percent',
            'value' => 50,
            'minimum_amount' => 0,
            'max_per_user' => 1,
            'is_active' => true,
        ]);
        $coupon->subscriptionPlans()->attach($plan->id);

        $this->postJson('/api/auth/register', [
            'name' => 'Partial Payment Student',
            'email' => 'partial-payment@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'student_phone' => '01012345679',
            'parent_phone' => '01112345679',
            'grade' => 'grade-1',
            'semester' => 'semester-1',
            'relationship_type' => 'father',
            'subscription_plan_id' => $plan->id,
            'promo_code' => 'halfpass',
            'payment_channel' => 'instapay',
            'transaction_id' => 'TX-PARTIAL-1001',
            'sender_identifier' => '01012345679',
            'transferred_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'receipt' => UploadedFile::fake()->image('partial-receipt.jpg'),
        ])->assertCreated()->assertJsonPath('application.status', 'pending_payment_review');

        $student = User::where('email', 'partial-payment@example.com')->firstOrFail();
        $this->assertFalse($student->is_active);
        $this->assertDatabaseHas('orders', [
            'user_id' => $student->id,
            'status' => 'submitted',
            'payment_provider' => 'manual_transfer',
            'payment_method' => 'instapay',
        ]);
        $this->assertSame(1, ManualPaymentSubmission::count());
    }

    public function test_full_discount_registration_rejects_promo_code_that_does_not_apply_to_selected_plan(): void
    {
        $selectedPlan = SubscriptionPlan::firstOrFail();
        $otherPlan = SubscriptionPlan::query()->whereKeyNot($selectedPlan->id)->firstOrFail();
        $coupon = Coupon::create([
            'code' => 'OTHERPLAN',
            'type' => 'percent',
            'value' => 100,
            'minimum_amount' => 0,
            'max_per_user' => 1,
            'is_active' => true,
        ]);
        $coupon->subscriptionPlans()->attach($otherPlan->id);

        $this->postJson('/api/auth/register', [
            'name' => 'Restricted Promo Student',
            'email' => 'restricted-promo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'student_phone' => '01012345680',
            'parent_phone' => '01112345680',
            'grade' => 'grade-1',
            'semester' => 'semester-1',
            'relationship_type' => 'father',
            'subscription_plan_id' => $selectedPlan->id,
            'promo_code' => 'otherplan',
        ])->assertUnprocessable()->assertJsonValidationErrors('promo_code');

        $this->assertDatabaseMissing('users', ['email' => 'restricted-promo@example.com']);
    }

    public function test_registration_rejects_privileged_roles(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123', 'student_phone' => '01012345678',
            'parent_phone' => '01112345678', 'grade' => 'grade-1', 'semester' => 'semester-1', 'relationship_type' => 'father',
            'role' => 'admin',
        ])->assertUnprocessable();
    }

    public function test_admin_accounts_cannot_login_through_the_public_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'password123']);

        $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_authenticated_user_can_update_their_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/profile', [
            'name' => 'Updated Student',
            'email' => 'updated@example.com',
        ])->assertOk()
            ->assertJsonPath('user.name', 'Updated Student')
            ->assertJsonPath('user.email', 'updated@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Student',
            'email' => 'updated@example.com',
            'email_verified_at' => null,
        ]);
    }

    public function test_profile_email_must_remain_unique(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/profile', [
            'name' => $user->name,
            'email' => $other->email,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_authenticated_user_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/password', [
            'current_password' => 'incorrect-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    }

    public function test_password_reset_changes_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $user->createToken('browser');
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertCount(0, $user->tokens()->get());
    }

    public function test_verification_link_marks_email_as_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect(config('app.frontend_url').'/email-verification?status=verified');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_authenticated_user_can_request_another_verification_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/verification-notification')->assertOk();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    private function courseFor(AcademicTerm $term, string $id): Course
    {
        $offering = AcademicSubject::where('academic_term_id', $term->id)->firstOrFail();

        return Course::create([
            'id' => $id,
            'title' => 'دورة',
            'title_en' => 'Course',
            'summary' => 'ملخص',
            'summary_en' => 'Summary',
            'category' => $offering->subject->name_en,
            'academic_subject_id' => $offering->id,
            'grade' => $term->grade->code,
            'semester' => $term->code,
            'level' => 'Beginner',
            'duration_minutes' => 60,
            'price' => 0,
            'instructor' => 'أحمد',
            'instructor_en' => 'Ahmed',
            'is_published' => true,
        ]);
    }
}
