<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\ManualPaymentSubmission;
use App\Models\PaymentReconciliationPeriod;
use App\Models\ReconciliationAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_admin_reconciles_and_closes_an_immutable_period(): void
    {
        $finance = User::factory()->create(['role' => 'admin', 'admin_role' => 'finance_manager']);
        $this->payment('REC-IN-1', 350, 'instapay', 'completed');
        $this->payment('REC-REF-1', 250, 'instapay', 'refunded');

        $this->actingAs($finance)->post(route('admin.reconciliation.store'), ['name' => 'Daily close', 'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString()])->assertRedirect();
        $period = PaymentReconciliationPeriod::with('lines')->firstOrFail();
        $instapay = $period->lines->firstWhere('channel', 'instapay');
        $this->assertSame('600.00', $instapay->expected_inflow);
        $this->assertSame('250.00', $instapay->expected_refunds);

        $lines = $period->lines->map(fn ($line) => ['channel' => $line->channel, 'actual_inflow' => $line->channel === 'instapay' ? 600 : 0, 'actual_refunds' => $line->channel === 'instapay' ? 250 : 0])->all();
        $this->actingAs($finance)->put(route('admin.reconciliation.update', $period), ['lines' => $lines])->assertRedirect();
        $this->actingAs($finance)->post(route('admin.reconciliation.close', $period), ['close_note' => 'Matched to statements.'])->assertRedirect();
        $this->assertDatabaseHas('payment_reconciliation_periods', ['id' => $period->id, 'status' => 'closed', 'closed_by' => $finance->id]);
        $this->assertSame(0.0, $period->lines()->where('channel', 'instapay')->firstOrFail()->variance);
        $this->actingAs($finance)->put(route('admin.reconciliation.update', $period), ['lines' => $lines])->assertStatus(409);
        $this->actingAs($finance)->get(route('admin.reconciliation.export', $period))->assertOk()->assertDownload();
    }

    public function test_periods_cannot_overlap_and_non_finance_staff_are_forbidden(): void
    {
        $finance = User::factory()->create(['role' => 'admin', 'admin_role' => 'finance_manager']);
        $payload = ['name' => 'Week one', 'starts_on' => '2026-08-01', 'ends_on' => '2026-08-07'];
        $this->actingAs($finance)->post(route('admin.reconciliation.store'), $payload)->assertRedirect();
        $this->actingAs($finance)->post(route('admin.reconciliation.store'), $payload)->assertSessionHasErrors('starts_on');
        $editor = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor']);
        $this->actingAs($editor)->get(route('admin.reconciliation.index'))->assertForbidden();
    }

    public function test_monitoring_creates_idempotent_variance_and_overdue_alerts_with_daily_summary(): void
    {
        $finance = User::factory()->create(['role' => 'admin', 'admin_role' => 'finance_manager']);
        $date = now()->subDays(3)->toDateString();
        $this->actingAs($finance)->post(route('admin.reconciliation.store'), ['name' => 'Overdue period', 'starts_on' => $date, 'ends_on' => $date])->assertRedirect();
        $period = PaymentReconciliationPeriod::with('lines')->firstOrFail();
        $lines = $period->lines->map(fn ($line) => ['channel' => $line->channel, 'actual_inflow' => $line->channel === 'instapay' ? 100 : 0, 'actual_refunds' => 0])->all();
        $this->actingAs($finance)->put(route('admin.reconciliation.update', $period), ['lines' => $lines])->assertRedirect();

        $this->artisan('payments:monitor-reconciliation --summary')->assertSuccessful();
        $this->assertDatabaseHas('reconciliation_alerts', ['period_id' => $period->id, 'type' => 'variance', 'channel' => 'instapay', 'resolved_at' => null]);
        $this->assertDatabaseHas('reconciliation_alerts', ['period_id' => $period->id, 'type' => 'overdue', 'resolved_at' => null]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $finance->id, 'type' => 'finance_daily_summary']);
        $this->artisan('payments:monitor-reconciliation --summary')->assertSuccessful();
        $this->assertDatabaseCount('reconciliation_alerts', 2);
        $this->assertSame(1, $finance->userNotifications()->where('type', 'finance_daily_summary')->count());

        $alert = ReconciliationAlert::where('type', 'variance')->firstOrFail();
        $this->actingAs($finance)->post(route('admin.reconciliation.alerts.resolve', $alert), ['resolution_note' => 'Verified against the bank statement.'])->assertRedirect();
        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    private function payment(string $reference, float $amount, string $channel, string $status): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::first() ?: Course::create(['id' => 'reconciliation-course', 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => $amount, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
        $order = $student->orders()->create(['public_id' => fake()->uuid(), 'course_id' => $course->id, 'amount' => $amount, 'original_amount' => $amount, 'currency' => 'EGP', 'status' => $status, 'payment_provider' => 'manual_transfer', 'payment_method' => $channel, 'session_token_hash' => hash('sha256', fake()->uuid()), 'paid_at' => now(), 'refunded_at' => $status === 'refunded' ? now() : null]);
        ManualPaymentSubmission::create(['order_id' => $order->id, 'user_id' => $student->id, 'channel' => $channel, 'transaction_id' => $reference, 'sender_identifier' => 'sender', 'transferred_at' => now(), 'submitted_amount' => $amount, 'receipt_path' => 'receipt.jpg', 'receipt_original_name' => 'receipt.jpg', 'receipt_mime' => 'image/jpeg', 'receipt_size' => 10, 'receipt_sha256' => hash('sha256', $reference), 'status' => 'approved', 'reviewed_at' => now()]);
    }
}
