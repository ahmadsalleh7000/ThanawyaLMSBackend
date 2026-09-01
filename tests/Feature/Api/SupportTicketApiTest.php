<?php

namespace Tests\Feature\Api;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_student_creates_and_replies_to_private_support_ticket(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($student);
        $response = $this->post('/api/support/tickets', [
            'subject' => 'The lesson video will not load', 'category' => 'technical', 'priority' => 'normal',
            'message' => 'The video remains blank after refreshing the lesson.',
            'attachment' => UploadedFile::fake()->image('screen.png'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.status', 'open');
        $ticketId = $response->json('data.id');
        $ticket = SupportTicket::firstOrFail();
        Storage::disk('local')->assertExists($ticket->messages()->firstOrFail()->attachment_path);

        $this->postJson("/api/support/tickets/{$ticketId}/messages", ['message' => 'The issue still happens in another browser.'])
            ->assertCreated()->assertJsonPath('data.messageCount', 2);
        $this->getJson("/api/support/tickets/{$ticketId}")->assertOk()->assertJsonCount(2, 'messages');

        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->getJson("/api/support/tickets/{$ticketId}")->assertNotFound();
    }

    public function test_admin_reply_updates_status_and_notifies_student(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $ticket = SupportTicket::create(['public_id' => fake()->uuid(), 'user_id' => $student->id, 'subject' => 'Payment question', 'category' => 'payment', 'priority' => 'urgent', 'status' => 'open', 'last_message_at' => now()]);
        $ticket->messages()->create(['user_id' => $student->id, 'body' => 'Please help me find my payment status.']);
        $support = User::factory()->create(['role' => 'admin', 'admin_role' => 'support_agent']);

        $this->actingAs($support)->post(route('admin.support-tickets.reply', $ticket), ['message' => 'We found your transfer and finance is reviewing it.', 'status' => 'waiting_student'])->assertRedirect();
        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'status' => 'waiting_student', 'assigned_to' => $support->id]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $student->id, 'type' => 'support_ticket', 'action_url' => '/support/'.$ticket->public_id]);
        $this->actingAs($support)->get(route('admin.support-tickets.show', $ticket))->assertOk()->assertSee('We found your transfer');
    }
}
