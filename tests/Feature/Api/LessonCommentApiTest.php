<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonCommentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_can_view_create_and_reply_without_spoofing_the_author(): void
    {
        [$student, $course, $lesson] = $this->enrolledLesson();
        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student);

        $created = $this->postJson($this->commentsUrl($course, $lesson), [
            'content' => '  <script>alert("xss")</script> A useful question.  ',
            'user_id' => $other->id,
        ])->assertCreated()
            ->assertJsonPath('data.author.id', $student->id)
            ->assertJsonPath('data.content', '<script>alert("xss")</script> A useful question.')
            ->json('data');

        $this->postJson($this->commentsUrl($course, $lesson).'/'.$created['id'].'/replies', [
            'content' => 'This is a helpful follow-up.',
            'user_id' => $other->id,
        ])->assertCreated()->assertJsonPath('data.author.id', $student->id);

        $this->getJson($this->commentsUrl($course, $lesson))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.commentCount', 2)
            ->assertJsonCount(1, 'data.0.replies')
            ->assertJsonMissingPath('data.0.author.email');
    }

    public function test_comment_access_requires_authentication_and_lesson_access(): void
    {
        [$student, $course, $lesson] = $this->enrolledLesson();
        $this->getJson($this->commentsUrl($course, $lesson))->assertUnauthorized();
        $this->postJson($this->commentsUrl($course, $lesson), ['content' => 'Anonymous comment.'])->assertUnauthorized();

        $outsider = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($outsider);
        $this->getJson($this->commentsUrl($course, $lesson))->assertForbidden();
        $this->postJson($this->commentsUrl($course, $lesson), ['content' => 'Unauthorized comment.'])->assertForbidden();
    }

    public function test_comment_content_is_trimmed_and_validated(): void
    {
        [$student, $course, $lesson] = $this->enrolledLesson();
        Sanctum::actingAs($student);

        $this->postJson($this->commentsUrl($course, $lesson), ['content' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->postJson($this->commentsUrl($course, $lesson), ['content' => str_repeat('a', 2001)])
            ->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->postJson($this->commentsUrl($course, $lesson), ['content' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors('content');
    }

    public function test_owner_can_edit_and_delete_but_another_student_cannot(): void
    {
        [$owner, $course, $lesson] = $this->enrolledLesson();
        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $other->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now(), 'status' => 'active']);
        $comment = LessonComment::create(['lesson_id' => $lesson->id, 'user_id' => $owner->id, 'content' => 'Original comment.']);

        Sanctum::actingAs($other);
        $this->patchJson($this->commentUrl($course, $lesson, $comment), ['content' => 'Stolen edit.'])->assertForbidden();
        $this->deleteJson($this->commentUrl($course, $lesson, $comment))->assertForbidden();

        Sanctum::actingAs($owner);
        $this->patchJson($this->commentUrl($course, $lesson, $comment), ['content' => 'Updated by owner.'])
            ->assertOk()->assertJsonPath('data.edited', true);
        $this->deleteJson($this->commentUrl($course, $lesson, $comment))->assertNoContent();
        $this->assertSoftDeleted($comment);
    }

    public function test_admin_can_moderate_but_cannot_edit_a_student_comment(): void
    {
        [$student, $course, $lesson] = $this->enrolledLesson();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'admin_role' => 'content_editor']);
        $comment = LessonComment::create(['lesson_id' => $lesson->id, 'user_id' => $student->id, 'content' => 'Needs moderation.']);
        Sanctum::actingAs($admin);

        $this->patchJson($this->commentUrl($course, $lesson, $comment), ['content' => 'Admin rewrite.'])->assertForbidden();
        $this->deleteJson($this->commentUrl($course, $lesson, $comment))->assertNoContent();
        $this->assertSoftDeleted($comment);
    }

    public function test_student_can_report_an_accessible_comment_once_and_admin_can_resolve_it(): void
    {
        [$student, $course, $lesson] = $this->enrolledLesson();
        $author = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $author->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now(), 'status' => 'active']);
        $comment = LessonComment::create(['lesson_id' => $lesson->id, 'user_id' => $author->id, 'content' => 'Please review this.']);
        Sanctum::actingAs($student);

        $url = $this->commentUrl($course, $lesson, $comment).'/reports';
        $this->postJson($url, ['reason' => 'inappropriate', 'details' => 'This needs review.'])
            ->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->postJson($url, ['reason' => 'spam'])->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseCount('lesson_comment_reports', 1);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'admin_role' => 'content_editor']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/comment-reports')->assertOk()
            ->assertJsonPath('data.0.comment.id', $comment->id)
            ->assertJsonPath('data.0.reporter.id', $student->id);
        $this->patchJson('/api/admin/comment-reports/1', ['status' => 'resolved', 'hide_comment' => true])
            ->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->assertSoftDeleted($comment);
    }

    public function test_unrelated_student_cannot_report_a_comment(): void
    {
        [$student, $course, $lesson] = $this->enrolledLesson();
        $comment = LessonComment::create(['lesson_id' => $lesson->id, 'user_id' => $student->id, 'content' => 'Private course comment.']);
        $outsider = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($outsider);

        $this->postJson($this->commentUrl($course, $lesson, $comment).'/reports', ['reason' => 'spam'])->assertForbidden();
    }

    public function test_replies_are_one_level_and_must_match_the_lesson(): void
    {
        [$student, $course, $lesson] = $this->enrolledLesson();
        $otherLesson = Lesson::factory()->for($course)->create(['slug' => 'other-lesson', 'position' => 2, 'status' => 'published']);
        $comment = LessonComment::create(['lesson_id' => $lesson->id, 'user_id' => $student->id, 'content' => 'Root comment.']);
        $reply = LessonComment::create(['lesson_id' => $lesson->id, 'user_id' => $student->id, 'parent_id' => $comment->id, 'content' => 'First reply.']);
        Sanctum::actingAs($student);

        $this->postJson($this->commentsUrl($course, $lesson).'/'.$reply->id.'/replies', ['content' => 'Nested reply.'])->assertUnprocessable();
        $this->postJson($this->commentsUrl($course, $otherLesson).'/'.$comment->id.'/replies', ['content' => 'Wrong lesson.'])->assertNotFound();
    }

    public function test_disabled_discussion_remains_readable_but_rejects_new_content(): void
    {
        [$student, $course, $lesson] = $this->enrolledLesson();
        $comment = LessonComment::create(['lesson_id' => $lesson->id, 'user_id' => $student->id, 'content' => 'Existing comment.']);
        $lesson->update(['comments_enabled' => false]);
        Sanctum::actingAs($student);

        $this->getJson($this->commentsUrl($course, $lesson))->assertOk()
            ->assertJsonPath('meta.commentsEnabled', false)
            ->assertJsonPath('data.0.id', $comment->id);
        $this->postJson($this->commentsUrl($course, $lesson), ['content' => 'Blocked comment.'])->assertStatus(409);
        $this->postJson($this->commentsUrl($course, $lesson).'/'.$comment->id.'/replies', ['content' => 'Blocked reply.'])->assertStatus(409);
    }

    public function test_comment_creation_is_rate_limited(): void
    {
        [$student, $course, $lesson] = $this->enrolledLesson();
        Sanctum::actingAs($student);
        for ($index = 1; $index <= 120; $index++) {
            $this->postJson($this->commentsUrl($course, $lesson), ['content' => "Comment {$index}"])->assertCreated();
        }
        $this->postJson($this->commentsUrl($course, $lesson), ['content' => 'Comment 121'])->assertTooManyRequests();
    }

    public function test_comments_are_paginated_newest_first(): void
    {
        [$student, $course, $lesson] = $this->enrolledLesson();
        foreach (range(1, 21) as $index) {
            LessonComment::create([
                'lesson_id' => $lesson->id,
                'user_id' => $student->id,
                'content' => "Paginated comment {$index}",
                'created_at' => now()->addSeconds($index),
            ]);
        }
        Sanctum::actingAs($student);

        $this->getJson($this->commentsUrl($course, $lesson))
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('data.0.content', 'Paginated comment 21')
            ->assertJsonPath('meta.currentPage', 1)
            ->assertJsonPath('meta.lastPage', 2)
            ->assertJsonPath('meta.total', 21);
        $this->getJson($this->commentsUrl($course, $lesson).'?page=2')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.content', 'Paginated comment 1');
    }

    private function enrolledLesson(): array
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $course = Course::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->for($course)->create(['slug' => 'comments-lesson', 'status' => 'published', 'comments_enabled' => true]);
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now(), 'status' => 'active']);

        return [$student, $course, $lesson];
    }

    private function commentsUrl(Course $course, Lesson $lesson): string
    {
        return "/api/courses/{$course->id}/lessons/{$lesson->slug}/comments";
    }

    private function commentUrl(Course $course, Lesson $lesson, LessonComment $comment): string
    {
        return $this->commentsUrl($course, $lesson).'/'.$comment->id;
    }
}
