<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCourseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_course_management(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->getJson('/api/admin/courses')->assertForbidden();
        $this->postJson('/api/admin/courses', $this->coursePayload('blocked'))->assertForbidden();
    }

    public function test_admin_can_create_edit_publish_and_archive_a_course_with_audit_history(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/api/admin/courses', $this->coursePayload('admin-python') + ['image_url' => '/images/custom-course.webp'])
            ->assertCreated()
            ->assertJsonPath('data.isPublished', false)
            ->assertJsonPath('data.imageUrl', '/images/custom-course.webp');
        $this->postJson('/api/admin/courses/admin-python/publish')->assertUnprocessable();
        $lesson = $this->postJson('/api/admin/courses/admin-python/lessons', [
            'slug' => 'intro', 'title' => 'مقدمة', 'title_en' => 'Introduction', 'duration_minutes' => 20, 'position' => 1, 'is_preview' => true,
        ])->assertCreated()->json('data');
        $this->postJson('/api/admin/courses/admin-python/publish')->assertOk()->assertJsonPath('data.isPublished', true);
        $this->getJson('/api/courses')->assertOk()->assertJsonPath('data.0.id', 'admin-python')->assertJsonPath('data.0.imageUrl', '/images/custom-course.webp');
        $this->patchJson('/api/admin/courses/admin-python', ['title_en' => 'Updated course'])->assertOk()->assertJsonPath('data.titleEn', 'Updated course');
        $this->deleteJson('/api/admin/courses/admin-python/lessons/'.$lesson['id'])->assertConflict();
        $this->postJson('/api/admin/courses/admin-python/archive')->assertOk()->assertJsonPath('data.isPublished', false);
        $this->getJson('/api/courses')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('audit_logs', ['action' => 'course.created', 'auditable_id' => 'admin-python']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'course.archived', 'auditable_id' => 'admin-python']);
    }

    public function test_admin_can_reorder_all_lessons_but_not_submit_a_partial_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = $this->course('reorder-course');
        $first = $course->lessons()->create(['slug' => 'one', 'title' => 'واحد', 'title_en' => 'One', 'duration_minutes' => 10, 'position' => 1]);
        $second = $course->lessons()->create(['slug' => 'two', 'title' => 'اثنان', 'title_en' => 'Two', 'duration_minutes' => 10, 'position' => 2]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/courses/'.$course->id.'/lessons/reorder', ['lesson_ids' => [$first->id]])->assertUnprocessable();
        $this->postJson('/api/admin/courses/'.$course->id.'/lessons/reorder', ['lesson_ids' => [$second->id, $first->id]])
            ->assertOk()->assertJsonPath('data.0.id', $second->id)->assertJsonPath('data.0.position', 1);
    }

    public function test_public_registration_cannot_create_an_admin(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Attacker', 'email' => 'attacker@example.test', 'password' => 'password', 'password_confirmation' => 'password', 'role' => 'admin'])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.test']);
    }

    private function coursePayload(string $id): array
    {
        return ['id' => $id, 'title' => 'دورة الإدارة', 'title_en' => 'Admin course', 'summary' => 'ملخص عربي', 'summary_en' => 'English summary', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 200, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed'];
    }

    private function course(string $id): Course
    {
        return Course::create($this->coursePayload($id) + ['is_published' => false]);
    }
}
