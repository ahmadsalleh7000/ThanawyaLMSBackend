<?php

namespace Tests\Feature;

use App\Events\GuardianInvitationCreated;
use App\Jobs\SendGuardianWelcomeCredentials;
use App\Models\GuardianInvitation;
use App\Models\ManualPaymentSubmission;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\GuardianWhatsAppHandoffService;
use App\Services\ManualPaymentReviewService;
use App\Services\StudentWhatsAppHandoffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformSetting::create(['key' => 'instapay_destination', 'value' => 'payments@instapay', 'group' => 'general']);
        cache()->forget('platform-settings');
    }

    public function test_student_registration_is_transactional_and_creates_a_guardian_invitation(): void
    {
        Event::fake([GuardianInvitationCreated::class]);
        User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->post('/api/auth/register', $this->validPayload(), ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('application.status', 'pending_payment_review')
            ->assertJsonMissingPath('token');
        $student = User::where('email', 'student@example.test')->firstOrFail();
        $invitation = GuardianInvitation::where('student_id', $student->id)->firstOrFail();

        $this->assertSame('+201112345678', $invitation->parent_phone_e164);
        $this->assertDatabaseHas('student_onboarding_profiles', [
            'student_id' => $student->id,
            'grade' => 'grade-1',
            'semester' => 'semester-1',
        ]);
        $this->assertFalse($student->is_active);
        $this->assertSame('pending_payment_review', $student->registration_status);
        $this->assertDatabaseHas('manual_payment_submissions', ['user_id' => $student->id, 'status' => 'submitted']);
        $this->assertDatabaseHas('user_notifications', [
            'type' => 'student_registration',
            'action_url' => '/admin/payment-reviews/'.$student->orders()->firstOrFail()->manualPaymentSubmissions()->firstOrFail()->id,
        ]);
        $this->postJson('/api/auth/login', ['email' => $student->email, 'password' => 'StrongPassword123!'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Your registration and payment are still under review.');
        Event::assertNotDispatched(GuardianInvitationCreated::class);
    }

    public function test_parent_phone_is_required_and_both_phone_fields_are_validated(): void
    {
        $this->post('/api/auth/register', $this->validPayload([
            'student_phone' => '01312345678',
            'parent_phone' => null,
        ]), ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['student_phone', 'parent_phone']);

        $this->assertDatabaseMissing('users', ['email' => 'student@example.test']);
    }

    public function test_approval_activates_and_links_accounts_without_initiating_whatsapp(): void
    {
        Queue::fake();
        $this->post('/api/auth/register', $this->validPayload(), ['Accept' => 'application/json'])->assertCreated();
        $student = User::where('email', 'student@example.test')->firstOrFail();
        $submission = ManualPaymentSubmission::where('user_id', $student->id)->firstOrFail();
        $reviewer = User::factory()->create(['role' => User::ROLE_ADMIN]);

        app(ManualPaymentReviewService::class)->approve($submission, $reviewer, 'Transfer confirmed against destination statement.');

        $this->assertTrue($student->fresh()->is_active);
        $this->assertSame('approved', $student->fresh()->registration_status);
        $this->assertSame($reviewer->id, $student->fresh()->registration_reviewed_by);
        $this->assertNotNull($student->fresh()->registration_reviewed_at);
        $this->assertDatabaseHas('orders', ['id' => $submission->order_id, 'status' => 'completed']);
        $guardian = User::where('phone_e164', '+201112345678')->firstOrFail();
        $this->assertSame(User::ROLE_PARENT, $guardian->role);
        $this->assertTrue($guardian->must_change_password);
        $this->assertNull($guardian->temporary_password_expires_at);
        $this->assertDatabaseHas('parent_student_links', ['parent_id' => $guardian->id, 'student_id' => $student->id, 'status' => 'accepted']);
        $this->assertDatabaseMissing('notification_deliveries', ['channel' => 'whatsapp']);
        $this->assertDatabaseMissing('notification_deliveries', ['channel' => 'whatsapp_web']);
        Queue::assertNotPushed(SendGuardianWelcomeCredentials::class);
    }

    public function test_rejection_keeps_the_student_inactive_and_records_the_decision(): void
    {
        $this->post('/api/auth/register', $this->validPayload(), ['Accept' => 'application/json'])->assertCreated();
        $student = User::where('email', 'student@example.test')->firstOrFail();
        $submission = ManualPaymentSubmission::where('user_id', $student->id)->firstOrFail();
        $reviewer = User::factory()->create(['role' => User::ROLE_ADMIN]);

        app(ManualPaymentReviewService::class)->decide(
            $submission,
            $reviewer,
            'rejected',
            'The transfer reference could not be matched. Please contact support.',
        );

        $student->refresh();
        $this->assertFalse($student->is_active);
        $this->assertSame('rejected', $student->registration_status);
        $this->assertSame($reviewer->id, $student->registration_reviewed_by);
        $this->assertSame('The transfer reference could not be matched. Please contact support.', $student->registration_rejection_reason);
        $this->postJson('/api/auth/login', ['email' => $student->email, 'password' => 'StrongPassword123!'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_admin_can_prepare_a_secure_guardian_whatsapp_web_handoff_after_approval(): void
    {
        Queue::fake();
        $this->post('/api/auth/register', $this->validPayload(), ['Accept' => 'application/json'])->assertCreated();
        $student = User::where('email', 'student@example.test')->firstOrFail();
        $submission = ManualPaymentSubmission::where('user_id', $student->id)->firstOrFail();
        $reviewer = User::factory()->create(['role' => User::ROLE_ADMIN]);
        app(ManualPaymentReviewService::class)->approve($submission, $reviewer, null);

        $invitation = $student->guardianInvitations()->with('student:id,name')->firstOrFail();
        $previousPasswordHash = $invitation->parent->password;
        $url = app(GuardianWhatsAppHandoffService::class)->prepare($invitation);
        $decodedUrl = rawurldecode($url);

        $this->assertStringStartsWith('https://web.whatsapp.com/send?phone=201112345678&text=', $url);
        $this->assertStringContainsString(rawurlencode('كلمة المرور المؤقتة'), $url);
        $this->assertStringContainsString('مرحباً بك في أكاديمية أحمد. تم اعتماد تسجيل Ahmed Student.', $decodedUrl);
        $this->assertStringContainsString('اسم الدخول: 01112345678', $decodedUrl);
        $this->assertStringContainsString('تسجيل الدخول: '.config('app.frontend_url').'/login', $decodedUrl);
        $this->assertStringContainsString('أنشئ كلمة مرور جديدة فور الدخول ولا تشارك بياناتك.', $decodedUrl);
        $this->assertDatabaseHas('notification_deliveries', [
            'guardian_invitation_id' => $invitation->id,
            'channel' => 'whatsapp_web',
            'status' => 'pending',
        ]);
        $this->assertNotSame($previousPasswordHash, $invitation->parent->fresh()->password);
        $this->assertNotNull($invitation->parent->fresh()->temporary_password_expires_at);
    }

    public function test_admin_can_open_student_whatsapp_without_exposing_the_student_password(): void
    {
        $this->post('/api/auth/register', $this->validPayload(), ['Accept' => 'application/json'])->assertCreated();
        $student = User::where('email', 'student@example.test')->firstOrFail();
        app(ManualPaymentReviewService::class)->approve(ManualPaymentSubmission::firstOrFail(), User::factory()->create(['role' => User::ROLE_ADMIN]), null);

        $url = app(StudentWhatsAppHandoffService::class)->prepare($student);
        $decoded = rawurldecode($url);

        $this->assertStringContainsString('01012345678', $decoded);
        $this->assertStringContainsString(config('app.frontend_url').'/login', $decoded);
        $this->assertStringNotContainsString('StrongPassword123!', $decoded);
        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $student->id,
            'template_key' => 'student_registration_approved',
            'status' => 'pending',
        ]);
    }

    public function test_approved_registration_exposes_separate_authorized_manual_whatsapp_actions(): void
    {
        $this->post('/api/auth/register', $this->validPayload(), ['Accept' => 'application/json'])->assertCreated();
        $student = User::where('email', 'student@example.test')->firstOrFail();
        $submission = ManualPaymentSubmission::firstOrFail();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        app(ManualPaymentReviewService::class)->approve($submission, $admin, null);

        $this->actingAs($admin)->get(route('admin.payment-reviews.show', $submission))
            ->assertOk()
            ->assertSee('فتح رسالة الطالب')
            ->assertSee('فتح رسالة ولي الأمر');

        $this->post(route('admin.payment-reviews.student-whatsapp', $submission))
            ->assertRedirectContains('https://web.whatsapp.com/send?phone=201012345678');
        $this->post(route('admin.payment-reviews.guardian-whatsapp', $submission))
            ->assertRedirectContains('https://web.whatsapp.com/send?phone=201112345678');

        $this->post(route('admin.payment-reviews.whatsapp.sent', [$submission, 'student']))
            ->assertRedirect(route('admin.payment-reviews.show', $submission));
        $studentDelivery = $student->notificationDeliveries()->where('template_key', 'student_registration_approved')->firstOrFail();
        $this->assertSame('sent', $studentDelivery->status->value);
        $this->assertNotNull($studentDelivery->sent_at);
        $this->assertSame($admin->id, $studentDelivery->sent_by);
        $this->post(route('admin.payment-reviews.whatsapp.sent', [$submission, 'guardian']))
            ->assertRedirect(route('admin.payment-reviews.show', $submission));
        $guardianDelivery = $student->guardianInvitations()->firstOrFail()->deliveries()->firstOrFail();
        $this->assertSame('sent', $guardianDelivery->status->value);
        $this->assertSame($admin->id, $guardianDelivery->sent_by);

        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'student_registration.whatsapp_opened']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'guardian_invitation.whatsapp_opened']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'student_whatsapp.marked_sent']);

        $this->actingAs($student)->post(route('admin.payment-reviews.student-whatsapp', $submission))->assertForbidden();
        $this->post(route('admin.payment-reviews.whatsapp.sent', [$submission, 'student']))->assertForbidden();
    }

    public function test_existing_guardian_is_linked_without_password_reset_for_a_sibling(): void
    {
        Queue::fake();
        $guardian = User::factory()->withEgyptianPhone('+201112345678')->create(['role' => User::ROLE_PARENT, 'password' => 'ExistingPassword123!', 'must_change_password' => false]);
        $passwordHash = $guardian->password;
        $this->post('/api/auth/register', $this->validPayload(), ['Accept' => 'application/json'])->assertCreated();
        $student = User::where('email', 'student@example.test')->firstOrFail();

        app(ManualPaymentReviewService::class)->approve(ManualPaymentSubmission::firstOrFail(), User::factory()->create(['role' => User::ROLE_ADMIN]), null);

        $this->assertDatabaseCount('users', 3);
        $this->assertSame($passwordHash, $guardian->fresh()->password);
        $this->assertDatabaseHas('parent_student_links', ['parent_id' => $guardian->id, 'student_id' => $student->id, 'status' => 'accepted']);
    }

    public function test_approval_reuses_a_legacy_system_guardian_email_and_repairs_its_phone(): void
    {
        Queue::fake();
        $guardian = User::factory()->create([
            'name' => 'Legacy Guardian',
            'email' => 'guardian-201112345678@guardian.ahmed-lms.local',
            'phone_e164' => null,
            'role' => User::ROLE_PARENT,
            'must_change_password' => true,
        ]);
        $this->post('/api/auth/register', $this->validPayload(), ['Accept' => 'application/json'])->assertCreated();
        $student = User::where('email', 'student@example.test')->firstOrFail();

        app(ManualPaymentReviewService::class)->approve(
            ManualPaymentSubmission::firstOrFail(),
            User::factory()->create(['role' => User::ROLE_ADMIN]),
            null,
        );

        $this->assertSame('+201112345678', $guardian->fresh()->phone_e164);
        $this->assertDatabaseHas('guardian_invitations', [
            'student_id' => $student->id,
            'parent_user_id' => $guardian->id,
            'guardian_account_was_created' => true,
        ]);
        $this->assertSame(3, User::count());
    }

    public function test_guardian_mobile_login_requires_and_completes_password_replacement(): void
    {
        Queue::fake();
        $this->post('/api/auth/register', $this->validPayload(), ['Accept' => 'application/json'])->assertCreated();
        app(ManualPaymentReviewService::class)->approve(ManualPaymentSubmission::firstOrFail(), User::factory()->create(['role' => User::ROLE_ADMIN]), null);
        $guardian = User::where('phone_e164', '+201112345678')->firstOrFail();
        $temporaryPassword = 'TemporarySafe123';
        $guardian->update(['password' => $temporaryPassword]);

        $token = $this->postJson('/api/auth/login', ['identity' => '01112345678', 'password' => $temporaryPassword])
            ->assertOk()->assertJsonPath('user.must_change_password', true)->json('token');
        $this->withToken($token)->getJson('/api/parent/students')->assertStatus(409)->assertJsonPath('code', 'password_change_required');
        $this->withToken($token)->putJson('/api/auth/forced-password', ['password' => 'MyPermanentPassword123!', 'password_confirmation' => 'MyPermanentPassword123!'])
            ->assertOk()->assertJsonPath('user.must_change_password', false);
        $this->assertTrue(Hash::check('MyPermanentPassword123!', $guardian->fresh()->password));
        $this->postJson('/api/auth/login', ['identity' => '01112345678', 'password' => $temporaryPassword])->assertUnprocessable();
    }

    public function test_student_and_parent_phone_numbers_must_be_different_after_normalization(): void
    {
        $this->post('/api/auth/register', $this->validPayload([
            'student_phone' => '01012345678',
            'parent_phone' => '+20 10 1234 5678',
        ]), ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('parent_phone');
    }

    public function test_other_guardian_relationship_requires_and_persists_its_details(): void
    {
        $this->post('/api/auth/register', $this->validPayload([
            'relationship_type' => 'other',
        ]), ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('relationship_details');

        $this->post('/api/auth/register', $this->validPayload([
            'email' => 'other-guardian@example.test',
            'student_phone' => '01098765432',
            'relationship_type' => 'other',
            'relationship_details' => 'Grandmother',
        ]), ['Accept' => 'application/json'])->assertCreated();

        $this->assertDatabaseHas('guardian_invitations', [
            'relationship_type' => 'other',
            'relationship_details' => 'Grandmother',
        ]);
    }

    public function test_duplicate_student_phone_is_rejected(): void
    {
        User::factory()->withEgyptianPhone('+201012345678')->create();

        $this->post('/api/auth/register', $this->validPayload(), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('student_phone');
    }

    public function test_existing_parent_is_reused_without_disclosing_account_existence(): void
    {
        $parent = User::factory()->withEgyptianPhone('+201112345678')->create([
            'role' => User::ROLE_PARENT,
        ]);

        $response = $this->post('/api/auth/register', $this->validPayload(), ['Accept' => 'application/json'])->assertCreated();
        $student = User::where('email', 'student@example.test')->firstOrFail();

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('guardian_invitations', [
            'student_id' => $student->id,
            'parent_user_id' => $parent->id,
            'parent_phone_e164' => '+201112345678',
            'status' => 'pending',
        ]);
        $response->assertJsonPath('application.status', 'pending_payment_review')
            ->assertJsonMissingPath('token');
    }

    public function test_non_parent_phone_owner_rejects_registration_without_partial_records(): void
    {
        User::factory()->withEgyptianPhone('+201112345678')->create([
            'role' => User::ROLE_STUDENT,
        ]);

        $this->post('/api/auth/register', $this->validPayload(), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_phone');

        $this->assertDatabaseMissing('users', ['email' => 'student@example.test']);
        $this->assertDatabaseCount('guardian_invitations', 0);
        $this->assertDatabaseCount('student_onboarding_profiles', 0);
    }

    public function test_public_registration_cannot_choose_an_account_role(): void
    {
        $this->post('/api/auth/register', $this->validPayload(['role' => 'parent']), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_registration_validation_contract_returns_all_supported_field_keys(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'A',
            'email' => 'not-an-email',
            'student_phone' => '01300000000',
            'parent_phone' => '01300000000',
            'grade' => 'unsupported',
            'relationship_type' => 'friend',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'name', 'email', 'student_phone', 'parent_phone', 'grade',
            'relationship_type', 'password',
        ]);
    }

    public function test_disabled_registration_returns_a_stable_forbidden_response_without_writes(): void
    {
        PlatformSetting::create(['key' => 'registration_enabled', 'value' => false, 'group' => 'access']);
        cache()->forget('platform-settings');

        $this->post('/api/auth/register', $this->validPayload(), ['Accept' => 'application/json'])
            ->assertForbidden()
            ->assertJsonPath('message', 'New account registration is temporarily disabled.');

        $this->assertDatabaseMissing('users', ['email' => 'student@example.test']);
        $this->assertDatabaseCount('guardian_invitations', 0);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Ahmed Student',
            'email' => 'student@example.test',
            'student_phone' => '01012345678',
            'parent_phone' => '01112345678',
            'grade' => 'grade-1',
            'semester' => 'semester-1',
            'relationship_type' => 'father',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'subscription_plan_id' => SubscriptionPlan::where('is_active', true)->firstOrFail()->id,
            'payment_channel' => 'instapay',
            'transaction_id' => 'TX-'.fake()->unique()->numerify('########'),
            'sender_identifier' => '01012345678',
            'transferred_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], $overrides);
    }
}
