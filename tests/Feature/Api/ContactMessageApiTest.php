<?php

namespace Tests\Feature\Api;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactMessageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_submit_a_contact_message(): void
    {
        $supportAdmin = User::factory()->create(['role' => 'admin', 'admin_role' => 'support_agent']);
        $contentAdmin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor']);

        $response = $this->postJson('/api/contact-messages', [
            'name' => 'Ahmed Visitor',
            'email' => 'visitor@example.com',
            'subject' => 'Question about the Python course',
            'message' => 'I would like to know when the next learning path begins.',
            'locale' => 'en',
            'website' => '',
        ])->assertCreated()->assertJsonStructure(['message', 'reference']);

        $this->assertDatabaseHas('contact_messages', ['email' => 'visitor@example.com', 'status' => 'new']);
        $this->assertMatchesRegularExpression('/^CONTACT-[A-Z0-9]{8}$/', $response->json('reference'));
        $this->assertNotSame('CONTACT-'.ContactMessage::first()->id, $response->json('reference'));
        $this->assertDatabaseHas('contact_messages', ['public_reference' => $response->json('reference')]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $supportAdmin->id,
            'type' => 'contact_message',
            'title_ar' => 'رسالة تواصل جديدة',
            'action_url' => '/admin/contact-messages/'.ContactMessage::first()->id,
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $contentAdmin->id,
            'type' => 'contact_message',
        ]);
    }

    public function test_authenticated_message_is_linked_to_the_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/contact-messages', [
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'Assistance with course progress',
            'message' => 'Please help me understand why my latest lesson is not complete.',
            'locale' => 'en',
        ])->assertCreated();

        $this->assertSame($user->id, ContactMessage::first()->user_id);
    }

    public function test_contact_message_rejects_spam_honeypot_and_short_content(): void
    {
        $this->withHeader('Accept-Language', 'ar')->postJson('/api/contact-messages', [
            'name' => 'Bot', 'email' => 'bot@example.com', 'subject' => 'Spam',
            'message' => 'Short', 'website' => 'https://spam.example.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['message', 'website'])
            ->assertJsonPath('errors.website.0', 'تعذر إرسال الرسالة. حدّث الصفحة ثم حاول مرة أخرى.');
    }

    public function test_contact_message_rate_limit_message_is_localized(): void
    {
        $visitorIp = '10.30.40.50';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => $visitorIp])
                ->withHeader('Accept-Language', 'ar')
                ->postJson('/api/contact-messages', [
                    'name' => 'Ahmed Visitor',
                    'email' => "visitor{$attempt}@example.com",
                    'subject' => 'Question about the Python course',
                    'message' => 'I would like to know when the next learning path begins.',
                    'locale' => 'ar',
                    'website' => '',
                ])->assertCreated();
        }

        $this->withServerVariables(['REMOTE_ADDR' => $visitorIp])
            ->withHeader('Accept-Language', 'ar')
            ->postJson('/api/contact-messages', [
                'name' => 'Ahmed Visitor',
                'email' => 'visitor-final@example.com',
                'subject' => 'Question about the Python course',
                'message' => 'I would like to know when the next learning path begins.',
                'locale' => 'ar',
                'website' => '',
            ])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'تم إرسال عدة محاولات خلال وقت قصير. انتظر قليلاً ثم حاول مرة أخرى.');
    }
}
