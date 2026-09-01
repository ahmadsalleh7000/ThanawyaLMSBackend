<?php

namespace Tests\Feature\Web;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminContactMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_admin_can_filter_view_and_resolve_contact_messages(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'support_agent']);
        $message = ContactMessage::create([
            'name' => 'Ahmed Visitor', 'email' => 'visitor@example.com',
            'subject' => 'Course access question',
            'message' => 'I need assistance opening my enrolled course.',
            'status' => 'new', 'locale' => 'en',
        ]);

        $this->actingAs($admin)->get(route('admin.contact-messages.index', ['status' => 'new']))
            ->assertOk()->assertSee('Course access question')->assertSee('visitor@example.com')->assertSee($message->public_reference);
        $this->get(route('admin.contact-messages.show', $message))->assertOk()->assertSee($message->message)->assertSee($message->public_reference);
        $this->put(route('admin.contact-messages.update', $message), [
            'status' => 'in_progress',
            'admin_note' => 'Waiting for the visitor to confirm the browser used.',
        ])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('contact_messages', [
            'id' => $message->id,
            'status' => 'in_progress',
            'admin_note' => 'Waiting for the visitor to confirm the browser used.',
            'handled_by' => $admin->id,
        ]);
        $this->assertNull($message->fresh()->resolved_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'contact_message.status_updated']);
    }

    public function test_admin_can_send_and_track_contact_message_reply(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'support_agent']);
        $message = ContactMessage::create([
            'name' => 'Ahmed Visitor', 'email' => 'visitor@example.com',
            'subject' => 'Course access question',
            'message' => 'I need assistance opening my enrolled course.',
            'status' => 'new', 'locale' => 'en',
        ]);

        $this->actingAs($admin)->post(route('admin.contact-messages.reply', $message), [
            'reply_body' => 'Thanks for reaching out. Please send us the course link you are opening.',
            'status' => 'resolved',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('contact_messages', [
            'id' => $message->id,
            'status' => 'resolved',
            'last_reply_body' => 'Thanks for reaching out. Please send us the course link you are opening.',
            'handled_by' => $admin->id,
            'replied_by' => $admin->id,
        ]);
        $this->assertNotNull($message->fresh()->replied_at);
        $this->assertNotNull($message->fresh()->resolved_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'contact_message.replied']);
    }

    public function test_contact_inbox_rejects_non_admin_users(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.contact-messages.index'))->assertForbidden();
    }

    public function test_dashboard_attention_queue_includes_open_contact_messages(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'support_agent']);
        ContactMessage::create([
            'name' => 'Ahmed Visitor', 'email' => 'visitor@example.com',
            'subject' => 'Course access question',
            'message' => 'I need assistance opening my enrolled course.',
            'status' => 'new', 'locale' => 'ar',
        ]);
        ContactMessage::create([
            'name' => 'Resolved Visitor', 'email' => 'done@example.com',
            'subject' => 'Done question',
            'message' => 'This message has already been resolved.',
            'status' => 'resolved', 'locale' => 'ar',
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('رسائل التواصل')
            ->assertSee('بانتظار المتابعة أو الرد')
            ->assertSee('رسائل تواصل بانتظار المتابعة')
            ->assertSee('رسائل زوار أو طلاب لم تُغلق بعد.');
    }
}
