<?php

namespace Tests\Feature\Web;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLessonCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_admin_can_view_reply_to_and_moderate_lesson_comments(): void
    {
        [$course, $lesson, $comment] = $this->commentFixture();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'admin_role' => 'content_editor']);

        $this->actingAs($admin)
            ->get(route('admin.lessons.edit', [$course, $lesson]).'?tab=discussion')
            ->assertOk()
            ->assertSee($comment->content)
            ->assertSee('تعليقات الدرس');

        $this->actingAs($admin)
            ->post(route('admin.lesson-comments.reply', [$course, $lesson, $comment]), ['content' => '  رد واضح من فريق المنصة.  '])
            ->assertRedirect();
        $reply = LessonComment::where('parent_id', $comment->id)->firstOrFail();
        $this->assertSame('رد واضح من فريق المنصة.', $reply->content);
        $this->assertSame($admin->id, $reply->user_id);

        $this->actingAs($admin)
            ->delete(route('admin.lesson-comments.destroy', [$course, $lesson, $comment]))
            ->assertRedirect();
        $this->assertSoftDeleted($comment);
        $this->assertSoftDeleted($reply);
    }

    public function test_student_cannot_open_or_use_admin_comment_moderation(): void
    {
        [$course, $lesson, $comment, $student] = $this->commentFixture();

        $this->actingAs($student)
            ->get(route('admin.lessons.edit', [$course, $lesson]).'?tab=discussion')
            ->assertForbidden();
        $this->actingAs($student)
            ->delete(route('admin.lesson-comments.destroy', [$course, $lesson, $comment]))
            ->assertForbidden();
    }

    public function test_admin_can_disable_new_comments_for_a_lesson(): void
    {
        [$course, $lesson] = $this->commentFixture();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'admin_role' => 'content_editor']);

        $this->actingAs($admin)->put(route('admin.lessons.update', [$course, $lesson]), [
            'comments_enabled' => false,
        ])->assertRedirect();

        $this->assertFalse($lesson->fresh()->comments_enabled);
    }

    private function commentFixture(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->for($course)->create(['status' => 'published', 'comments_enabled' => true]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $comment = LessonComment::create([
            'lesson_id' => $lesson->id,
            'user_id' => $student->id,
            'content' => 'سؤال طالب يحتاج إلى توضيح.',
        ]);

        return [$course, $lesson, $comment, $student];
    }
}
