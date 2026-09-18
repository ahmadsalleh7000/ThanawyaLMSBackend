<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonFeedbackApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_save_and_update_one_feedback_record_for_an_accessible_lesson(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $course = Course::factory()->create(['id' => 'feedback-course']);
        $lesson = $course->lessons()->create(['slug' => 'feedback-lesson', 'title' => 'Feedback', 'title_en' => 'Feedback', 'position' => 1]);
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        Sanctum::actingAs($student);

        $this->putJson('/api/courses/'.$course->id.'/lessons/'.$lesson->slug.'/feedback', ['rating' => 4, 'comment' => 'Clear explanation.'])
            ->assertOk()->assertJsonPath('data.rating', 4);
        $this->putJson('/api/courses/'.$course->id.'/lessons/'.$lesson->slug.'/feedback', ['rating' => 5, 'comment' => 'Very helpful.'])
            ->assertOk()->assertJsonPath('data.rating', 5);
        $this->getJson('/api/courses/'.$course->id.'/lessons/'.$lesson->slug.'/feedback')
            ->assertOk()->assertJsonPath('data.comment', 'Very helpful.');

        $this->assertDatabaseCount('lesson_feedback', 1);
    }

    public function test_feedback_is_rejected_for_an_inaccessible_lesson(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $otherStudent = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $course = Course::factory()->create(['id' => 'private-feedback-course']);
        $lesson = $course->lessons()->create(['slug' => 'private-feedback-lesson', 'title' => 'Private', 'title_en' => 'Private', 'position' => 1]);
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        Sanctum::actingAs($student);
        $this->putJson('/api/courses/'.$course->id.'/lessons/'.$lesson->slug.'/feedback', ['rating' => 3])->assertOk();

        Sanctum::actingAs($otherStudent);
        $this->getJson('/api/courses/'.$course->id.'/lessons/'.$lesson->slug.'/feedback')->assertForbidden();
        $this->putJson('/api/courses/'.$course->id.'/lessons/'.$lesson->slug.'/feedback', ['rating' => 1])->assertForbidden();
    }
}
