<?php

namespace Tests\Feature\Web;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLessonRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_restore_a_previous_lesson_snapshot(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $course = Course::factory()->create(['id' => 'revision-course']);
        $lesson = Lesson::factory()->create([
            'course_id' => $course->id,
            'slug' => 'revision-lesson',
            'title' => 'Original title',
            'title_en' => 'Original title',
        ]);

        $this->actingAs($admin)->put(route('admin.lessons.update', [$course, $lesson]), [
            'slug' => $lesson->slug,
            'title' => 'Changed title',
            'title_en' => 'Changed title',
            'duration_minutes' => $lesson->duration_minutes,
            'position' => $lesson->position,
        ])->assertRedirect();

        $revision = $lesson->revisions()->firstOrFail();
        $this->assertSame('Original title', $revision->snapshot['title']);

        $this->actingAs($admin)->post(route('admin.lessons.revisions.restore', [$course, $lesson, $revision]))
            ->assertRedirect();

        $this->assertSame('Original title', $lesson->refresh()->title);
        $this->assertDatabaseCount('lesson_revisions', 2);
    }
}
