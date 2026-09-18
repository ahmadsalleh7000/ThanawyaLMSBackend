<?php

namespace Tests\Feature\Api;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EngagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_toggle_only_published_courses_in_their_wishlist(): void
    {
        $user = User::factory()->create();
        $course = $this->course('saved-course');
        $hidden = $this->course('hidden-course', false);
        Sanctum::actingAs($user);

        $this->postJson('/api/me/wishlist/'.$course->id)->assertOk()->assertJsonPath('data.saved', true);
        $this->getJson('/api/me/wishlist')->assertOk()->assertExactJson(['data' => [$course->id]]);
        $this->postJson('/api/me/wishlist/'.$course->id)->assertOk()->assertJsonPath('data.saved', false);
        $this->postJson('/api/me/wishlist/'.$hidden->id)->assertNotFound();
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_wishlist_is_private_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $visitor = User::factory()->create();
        $course = $this->course('private-wish');
        $owner->wishlistItems()->create(['course_id' => $course->id]);
        Sanctum::actingAs($visitor);

        $this->getJson('/api/me/wishlist')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_user_can_read_their_notifications_and_mark_them_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->notification($user);
        Sanctum::actingAs($user);

        $this->getJson('/api/me/notifications')->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.isRead', false)
            ->assertJsonPath('meta.unreadCount', 1);
        $this->patchJson('/api/me/notifications/'.$notification->id.'/read')->assertOk()->assertJsonPath('data.isRead', true);
        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_user_cannot_update_another_users_notification_and_can_mark_all_own_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $own = $this->notification($user);
        $foreign = $this->notification($other);
        Sanctum::actingAs($user);

        $this->patchJson('/api/me/notifications/'.$foreign->id.'/read')->assertNotFound();
        $this->postJson('/api/me/notifications/read-all')->assertOk()->assertJsonPath('data.updated', 1);
        $this->assertNotNull($own->refresh()->read_at);
        $this->assertNull($foreign->refresh()->read_at);
    }

    public function test_dashboard_announcements_are_owner_scoped_unread_and_unexpired(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $active = $this->announcement(['is_banner' => true, 'expires_at' => now()->addHour()]);
        $expired = $this->announcement(['expires_at' => now()->subMinute()]);
        $read = $this->announcement();
        $activeNotification = $this->announcementNotification($user, $active);
        $this->announcementNotification($user, $expired);
        $this->announcementNotification($user, $read, now());
        $this->announcementNotification($other, $active);
        Sanctum::actingAs($user);

        $this->getJson('/api/me/announcements')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeNotification->id)
            ->assertJsonPath('data.0.isBanner', true)
            ->assertJsonPath('data.0.data.targetType', 'students')
            ->assertJsonMissing(['message' => 'Expired message']);

        $this->patchJson('/api/me/notifications/'.$activeNotification->id.'/read')->assertOk();
        $this->getJson('/api/me/announcements')->assertOk()->assertJsonCount(0, 'data');
    }

    private function course(string $id, bool $published = true): Course
    {
        return Course::create(['id' => $id, 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => $published]);
    }

    private function notification(User $user): UserNotification
    {
        return $user->userNotifications()->create(['type' => 'course', 'title' => 'New lesson', 'title_ar' => 'درس جديد', 'message' => 'A lesson is ready.', 'message_ar' => 'الدرس جاهز.']);
    }

    private function announcement(array $attributes = []): Announcement
    {
        return Announcement::create($attributes + [
            'title' => 'Current announcement',
            'title_ar' => 'إعلان حالي',
            'message' => 'Current message',
            'message_ar' => 'رسالة حالية',
            'target_type' => 'students',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    private function announcementNotification(User $user, Announcement $announcement, $readAt = null): UserNotification
    {
        return $user->userNotifications()->create([
            'announcement_id' => $announcement->id,
            'type' => 'announcement',
            'title' => $announcement->title,
            'title_ar' => $announcement->title_ar,
            'message' => $announcement->message,
            'message_ar' => $announcement->message_ar,
            'data' => ['targetType' => $announcement->target_type],
            'read_at' => $readAt,
        ]);
    }
}
