<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationPreferenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_read_and_update_notification_preferences(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($student);

        $this->getJson('/api/me/notification-preferences')->assertOk()
            ->assertJsonPath('data.learningReminders', true)
            ->assertJsonPath('data.timezone', 'Africa/Cairo');
        $this->putJson('/api/me/notification-preferences', [
            'payment_updates' => true,
            'course_updates' => false,
            'learning_reminders' => true,
            'parent_progress_summaries' => false,
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '21:30',
            'quiet_hours_end' => '07:30',
            'reminder_time' => '18:00',
            'weekly_summary_day' => 5,
            'timezone' => 'Africa/Cairo',
        ])->assertOk()->assertJsonPath('data.quietHoursEnabled', true);
        $this->assertDatabaseHas('notification_preferences', ['user_id' => $student->id, 'course_updates' => false, 'quiet_hours_start' => '21:30']);
    }

    public function test_due_learning_reminder_respects_quiet_hours(): void
    {
        Carbon::setTestNow('2026-08-11 18:30:00');
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true]);
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now(), 'status' => 'active']);
        $preference = NotificationPreference::create(['user_id' => $student->id, 'learning_reminders' => true, 'reminder_time' => '18:00', 'quiet_hours_enabled' => true, 'quiet_hours_start' => '21:00', 'quiet_hours_end' => '22:00']);

        $this->artisan('engagement:send-notifications')->assertSuccessful();
        $this->assertDatabaseCount('user_notifications', 0);
        $preference->update(['quiet_hours_enabled' => false]);
        $this->artisan('engagement:send-notifications')->assertSuccessful();
        $this->assertDatabaseHas('user_notifications', ['user_id' => $student->id, 'type' => 'learning_reminder']);
        Carbon::setTestNow();
    }

    public function test_parent_receives_a_weekly_progress_summary_only_once(): void
    {
        Carbon::setTestNow('2026-08-14 09:30:00');
        $parent = User::factory()->create(['role' => 'parent']);
        $student = User::factory()->create(['role' => 'student']);
        $parent->linkedStudents()->attach($student->id, ['grade' => 'grade-1', 'status' => 'accepted', 'linked_at' => now(), 'accepted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        NotificationPreference::create(['user_id' => $parent->id, 'parent_progress_summaries' => true, 'weekly_summary_day' => 5, 'quiet_hours_enabled' => false]);

        $this->artisan('engagement:send-notifications')->assertSuccessful();
        $this->artisan('engagement:send-notifications')->assertSuccessful();
        $this->assertDatabaseCount('user_notifications', 1);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $parent->id, 'type' => 'parent_summary']);
        Carbon::setTestNow();
    }
}
