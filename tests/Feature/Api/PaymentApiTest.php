<?php

namespace Tests\Feature\Api;

use App\Models\Coupon;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_checkout_uses_server_price_and_confirmation_enrolls_once(): void
    {
        config(['payments.provider' => 'local']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course('paid-course', 350);
        Sanctum::actingAs($student);

        $session = $this->postJson('/api/payments/checkout', ['course_id' => $course->id, 'payment_method' => 'card', 'amount' => 1])
            ->assertCreated()->assertJsonStructure(['session_id', 'redirect_url'])->json('session_id');
        $this->assertDatabaseHas('orders', ['user_id' => $student->id, 'course_id' => $course->id, 'amount' => 350, 'status' => 'pending']);

        $this->postJson('/api/payments/confirm', ['session_id' => $session])->assertOk()->assertJsonPath('status', 'completed');
        $this->postJson('/api/payments/confirm', ['session_id' => $session])->assertOk()->assertJsonPath('status', 'completed');
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('user_notifications', 1);
    }

    public function test_provider_payload_uses_text_storage_and_round_trips_as_an_encrypted_array(): void
    {
        config(['payments.provider' => 'local']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course('encrypted-provider-payload', 350);
        Sanctum::actingAs($student);

        $session = $this->postJson('/api/payments/checkout', [
            'course_id' => $course->id,
            'payment_method' => 'card',
        ])->assertCreated()->json('session_id');

        $this->postJson('/api/payments/confirm', ['session_id' => $session])->assertOk();

        $order = $student->orders()->firstOrFail()->fresh();
        $this->assertContains(Schema::getColumnType('orders', 'provider_payload'), ['text', 'longtext']);
        $this->assertIsArray($order->provider_payload);
        $this->assertNotEmpty($order->provider_payload);

        $raw = DB::table('orders')->where('id', $order->id)->value('provider_payload');
        $this->assertIsString($raw);
        $this->assertNull(json_decode($raw, true));
    }

    public function test_payment_session_is_private_and_opaque(): void
    {
        config(['payments.provider' => 'local']);
        $owner = User::factory()->create(['role' => 'student']);
        $attacker = User::factory()->create(['role' => 'student']);
        $course = $this->course('private-payment', 200);
        Sanctum::actingAs($owner);
        $session = $this->postJson('/api/payments/checkout', ['course_id' => $course->id, 'payment_method' => 'card'])->json('session_id');
        $this->assertDatabaseMissing('orders', ['session_token_hash' => $session]);

        Sanctum::actingAs($attacker);
        $this->postJson('/api/payments/confirm', ['session_id' => $session])->assertNotFound();
        $this->assertDatabaseMissing('enrollments', ['user_id' => $owner->id, 'course_id' => $course->id]);
    }

    public function test_coupon_price_is_calculated_by_server_and_invoice_is_issued(): void
    {
        config(['payments.provider' => 'local']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course('discounted-course', 500);
        Coupon::create(['code' => 'LEARN20', 'type' => 'percent', 'value' => 20, 'minimum_amount' => 100, 'max_per_user' => 1, 'is_active' => true]);
        Sanctum::actingAs($student);

        $session = $this->postJson('/api/payments/checkout', ['course_id' => $course->id, 'coupon_code' => 'learn20', 'payment_method' => 'card'])
            ->assertCreated()->json('session_id');
        $order = $student->orders()->firstOrFail();
        $this->assertSame('400.00', $order->amount);
        $this->assertSame('100.00', $order->discount_amount);
        $this->assertDatabaseHas('coupon_redemptions', ['order_id' => $order->id, 'status' => 'reserved']);

        $this->postJson('/api/payments/confirm', ['session_id' => $session])
            ->assertOk()->assertJsonPath('discountAmount', 100)->assertJsonPath('invoiceNumber', 'INV-'.now()->format('Ym').'-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT));
        $this->assertDatabaseHas('coupon_redemptions', ['order_id' => $order->id, 'status' => 'redeemed']);
        $this->assertDatabaseHas('invoices', ['order_id' => $order->id, 'total' => 400]);
    }

    public function test_checkout_rejects_exhausted_coupon_without_creating_an_order(): void
    {
        config(['payments.provider' => 'local']);
        $student = User::factory()->create(['role' => 'student']);
        $previousStudent = User::factory()->create(['role' => 'student']);
        $course = $this->course('exhausted-coupon-course', 500);
        $coupon = Coupon::create([
            'code' => 'LASTSEAT',
            'type' => 'percent',
            'value' => 20,
            'minimum_amount' => 0,
            'max_redemptions' => 1,
            'max_per_user' => 1,
            'is_active' => true,
        ]);
        $previousOrder = $previousStudent->orders()->create([
            'public_id' => fake()->uuid(),
            'course_id' => $course->id,
            'coupon_id' => $coupon->id,
            'amount' => 400,
            'original_amount' => 500,
            'discount_amount' => 100,
            'currency' => 'EGP',
            'status' => 'pending',
            'payment_provider' => 'local',
            'payment_method' => 'card',
            'session_token_hash' => hash('sha256', 'previous-lastseat-order'),
        ]);
        $coupon->redemptions()->create([
            'user_id' => $previousStudent->id,
            'order_id' => $previousOrder->id,
            'discount_amount' => 100,
            'status' => 'reserved',
        ]);
        Sanctum::actingAs($student);

        $this->postJson('/api/payments/checkout', [
            'course_id' => $course->id,
            'coupon_code' => 'lastseat',
            'payment_method' => 'card',
        ])->assertUnprocessable()->assertJsonValidationErrors('coupon_code');

        $this->assertDatabaseMissing('orders', ['user_id' => $student->id, 'coupon_id' => $coupon->id]);
    }

    public function test_checkout_rejects_duplicate_coupon_use_by_same_account_without_creating_an_order(): void
    {
        config(['payments.provider' => 'local']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course('duplicate-coupon-course', 500);
        $coupon = Coupon::create([
            'code' => 'ONCEONLY',
            'type' => 'fixed',
            'value' => 100,
            'minimum_amount' => 0,
            'max_per_user' => 1,
            'is_active' => true,
        ]);
        $previousOrder = $student->orders()->create([
            'public_id' => fake()->uuid(),
            'course_id' => $course->id,
            'coupon_id' => $coupon->id,
            'amount' => 400,
            'original_amount' => 500,
            'discount_amount' => 100,
            'currency' => 'EGP',
            'status' => 'pending',
            'payment_provider' => 'local',
            'payment_method' => 'card',
            'session_token_hash' => hash('sha256', 'previous-onceonly-order'),
        ]);
        $coupon->redemptions()->create([
            'user_id' => $student->id,
            'order_id' => $previousOrder->id,
            'discount_amount' => 100,
            'status' => 'reserved',
        ]);
        Sanctum::actingAs($student);

        $this->postJson('/api/payments/checkout', [
            'course_id' => $course->id,
            'coupon_code' => 'onceonly',
            'payment_method' => 'card',
        ])->assertUnprocessable()->assertJsonValidationErrors('coupon_code');

        $this->assertSame(1, $student->orders()->where('coupon_id', $coupon->id)->count());
    }

    public function test_bundle_checkout_enrolls_every_course(): void
    {
        config(['payments.provider' => 'local']);
        $student = User::factory()->create(['role' => 'student']);
        $first = $this->course('bundle-first', 300);
        $second = $this->course('bundle-second', 400);
        $bundle = CourseBundle::create(['name' => 'باقة', 'name_en' => 'Bundle', 'price' => 550, 'is_active' => true]);
        $bundle->courses()->attach([$first->id, $second->id]);
        Sanctum::actingAs($student);

        $session = $this->postJson('/api/payments/checkout', ['product_type' => 'bundle', 'bundle_id' => $bundle->id, 'payment_method' => 'card'])
            ->assertCreated()->json('session_id');
        $this->postJson('/api/payments/confirm', ['session_id' => $session])->assertOk()->assertJsonPath('productType', 'bundle');

        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $first->id]);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $second->id]);
        $this->assertCount(2, $student->orders()->firstOrFail()->invoice->line_items);
    }

    public function test_free_courses_cannot_use_paid_checkout_and_parent_must_select_a_linked_student(): void
    {
        config(['payments.provider' => 'local']);
        $free = $this->course('free-course', 0);
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->postJson('/api/payments/checkout', ['course_id' => $free->id, 'payment_method' => 'card'])->assertUnprocessable();

        Sanctum::actingAs(User::factory()->create(['role' => 'parent']));
        $this->postJson('/api/payments/checkout', ['course_id' => $this->course('parent-course', 100)->id, 'payment_method' => 'card'])->assertUnprocessable();
    }

    public function test_stripe_confirmation_requires_matching_server_verified_payment_data(): void
    {
        config(['payments.provider' => 'stripe', 'payments.stripe.secret' => 'sk_test_example', 'payments.stripe.api_url' => 'https://stripe.test/v1']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course('stripe-course', 425);
        Sanctum::actingAs($student);
        Http::fake(function (ClientRequest $request) use ($student) {
            if ($request->method() === 'POST') {
                return Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.test/session'], 200);
            }

            $order = $student->orders()->firstOrFail();

            return Http::response([
                'payment_status' => 'paid', 'amount_total' => 42500, 'currency' => 'egp', 'payment_intent' => 'pi_1',
                'metadata' => ['order_id' => $order->public_id, 'user_id' => (string) $student->id],
            ], 200);
        });

        $session = $this->postJson('/api/payments/checkout', ['course_id' => $course->id, 'payment_method' => 'card'])->assertCreated()->json('session_id');
        $this->postJson('/api/payments/confirm', ['session_id' => $session])->assertOk()->assertJsonPath('status', 'completed');
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
    }

    public function test_signed_stripe_webhook_completes_order_without_browser_confirmation(): void
    {
        config(['payments.provider' => 'stripe', 'payments.stripe.secret' => 'sk_test_example', 'payments.stripe.webhook_secret' => 'whsec_example', 'payments.stripe.api_url' => 'https://stripe.test/v1']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course('webhook-course', 500);
        Sanctum::actingAs($student);
        Http::fake(['*' => Http::response(['id' => 'cs_webhook_1', 'url' => 'https://checkout.stripe.test/session'], 200)]);
        $this->postJson('/api/payments/checkout', ['course_id' => $course->id, 'payment_method' => 'card'])->assertCreated();
        $order = $student->orders()->firstOrFail();
        $payload = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_webhook_1', 'payment_status' => 'paid', 'amount_total' => 50000, 'currency' => 'egp', 'payment_intent' => 'pi_webhook_1',
            'metadata' => ['order_id' => $order->public_id, 'user_id' => (string) $student->id],
        ]]], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_example');

        $this->call('POST', '/api/payments/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature], $payload)
            ->assertOk()->assertJsonPath('received', true);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'completed']);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
    }

    public function test_stripe_webhook_rejects_invalid_signatures(): void
    {
        config(['payments.stripe.webhook_secret' => 'whsec_example']);
        $payload = json_encode(['type' => 'checkout.session.completed'], JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/payments/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=invalid'], $payload)
            ->assertBadRequest();
    }

    private function course(string $id, float $price): Course
    {
        return Course::create(['id' => $id, 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => $price, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
    }
}
