<?php

namespace Tests\Feature;

use App\Enums\NotificationDeliveryStatus;
use App\Jobs\SendNotificationEmail;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\EmailDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_snapshots_template_and_queues_delivery(): void
    {
        Queue::fake();
        $student = User::factory()->create(['role' => 'student', 'name' => 'Ahmed Student']);
        $delivery = app(EmailDeliveryService::class)->queue($student, 'payment_approved', ['order' => 'ORDER-100'], 'en');

        $this->assertNotNull($delivery);
        $this->assertSame(NotificationDeliveryStatus::Queued, $delivery->status);
        $this->assertStringContainsString('ORDER-100', $delivery->body);
        Queue::assertPushed(SendNotificationEmail::class, fn ($job) => $job->deliveryId === $delivery->id);
    }

    public function test_email_job_sends_snapshot_and_marks_delivery_sent(): void
    {
        Event::fake([MessageSent::class]);
        $student = User::factory()->create(['role' => 'student']);
        Queue::fake();
        $delivery = app(EmailDeliveryService::class)->queue($student, 'payment_approved', ['order' => 'ORDER-200'], 'en');

        (new SendNotificationEmail($delivery->id))->handle();
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'sent', 'attempts' => 1]);
        Event::assertDispatched(MessageSent::class);
    }

    public function test_system_admin_manages_templates_and_retries_failed_delivery(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => null]);
        $template = NotificationTemplate::where('key', 'payment_rejected')->firstOrFail();
        $delivery = NotificationDelivery::create(['user_id' => $admin->id, 'template_id' => $template->id, 'template_key' => $template->key, 'recipient' => $admin->email, 'locale' => 'ar', 'subject' => 'Failed', 'body' => 'Body', 'status' => 'failed', 'attempts' => 3, 'last_error' => 'SMTP unavailable', 'failed_at' => now()]);

        $this->actingAs($admin)->get(route('admin.system.notifications.index'))->assertOk()->assertSee('SMTP unavailable');
        $this->actingAs($admin)->put(route('admin.system.notifications.templates.update', $template), ['subject_ar' => 'عنوان جديد', 'subject_en' => 'New subject', 'body_ar' => 'نص جديد {{name}}', 'body_en' => 'New body {{name}}', 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('notification_templates', ['id' => $template->id, 'subject_en' => 'New subject']);
        $this->actingAs($admin)->post(route('admin.system.notifications.retry', $delivery))->assertRedirect();
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'queued', 'attempts' => 0, 'last_error' => null]);
        Queue::assertPushed(SendNotificationEmail::class);

        $editor = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor']);
        $this->actingAs($editor)->get(route('admin.system.notifications.index'))->assertForbidden();
    }
}
