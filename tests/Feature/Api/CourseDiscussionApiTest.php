<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\LessonComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseDiscussionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_can_create_and_reply_to_a_course_topic(): void
    {
        [$student, $course] = $this->enrolledCourse();
        Sanctum::actingAs($student);
        $created = $this->postJson('/api/courses/'.$course->id.'/discussion', ['content' => 'How should I plan this course?'])
            ->assertCreated()->assertJsonPath('data.author.id', $student->id)->json('data');
        $this->postJson('/api/courses/'.$course->id.'/discussion/'.$created['id'].'/replies', ['content' => 'Start with the first unit.'])
            ->assertCreated()->assertJsonPath('data.author.id', $student->id);
        $this->getJson('/api/courses/'.$course->id.'/discussion')->assertOk()->assertJsonPath('meta.commentCount', 2)->assertJsonCount(1, 'data.0.replies');
        $this->assertDatabaseHas('lesson_comments', ['course_id' => $course->id, 'lesson_id' => null]);
    }

    public function test_course_discussion_requires_course_access_and_scopes_replies(): void
    {
        [$student, $course] = $this->enrolledCourse();
        $outsider = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $comment = LessonComment::create(['course_id' => $course->id, 'user_id' => $student->id, 'content' => 'Course topic']);
        Sanctum::actingAs($outsider);
        $this->getJson('/api/courses/'.$course->id.'/discussion')->assertForbidden();
        $this->postJson('/api/courses/'.$course->id.'/discussion/'.$comment->id.'/replies', ['content' => 'Not allowed'])->assertForbidden();
    }

    private function enrolledCourse(): array
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $course = Course::factory()->create(['id' => 'discussion-course']);
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);

        return [$student, $course];
    }
}
