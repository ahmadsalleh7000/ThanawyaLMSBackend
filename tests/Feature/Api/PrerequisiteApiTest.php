<?php

namespace Tests\Feature\Api;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\TrainingAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrerequisiteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_prerequisite_blocks_enrollment_until_required_progress_is_reached(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $source = Course::factory()->create(['id' => 'prerequisite-source']);
        $lesson = $source->lessons()->create(['slug' => 'source-lesson', 'title' => 'Source', 'title_en' => 'Source', 'position' => 1]);
        $target = Course::factory()->create(['id' => 'prerequisite-target', 'price' => 0]);
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/courses/'.$target->id.'/prerequisites', ['prerequisite_course_id' => $source->id, 'minimum_progress' => 100])->assertCreated();
        Sanctum::actingAs($student);
        $this->postJson('/api/courses/'.$target->id.'/enroll')->assertUnprocessable();
        $student->lessonCompletions()->create(['lesson_id' => $lesson->id, 'completed_at' => now()]);
        $this->postJson('/api/courses/'.$target->id.'/enroll')->assertCreated();
        $this->assertDatabaseHas('course_prerequisites', ['course_id' => $target->id, 'prerequisite_course_id' => $source->id]);
    }

    public function test_lesson_prerequisite_blocks_direct_lesson_content_access(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $course = Course::factory()->create(['id' => 'lesson-prereq-course']);
        $first = $course->lessons()->create(['slug' => 'first', 'title' => 'First', 'title_en' => 'First', 'position' => 1]);
        $second = $course->lessons()->create(['slug' => 'second', 'title' => 'Second', 'title_en' => 'Second', 'position' => 2]);
        $assignment = Assignment::create(['lesson_id' => $second->id, 'title' => 'Second task', 'title_en' => 'Second task', 'is_published' => true, 'position' => 1]);
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/lessons/'.$second->id.'/prerequisites', ['prerequisite_lesson_id' => $first->id])->assertCreated();
        Sanctum::actingAs($student);
        $this->getJson('/api/courses/'.$course->id.'/lessons/second/assignments/'.$assignment->id)->assertForbidden();
        $student->lessonCompletions()->create(['lesson_id' => $first->id, 'completed_at' => now()]);
        $this->getJson('/api/courses/'.$course->id.'/lessons/second/assignments/'.$assignment->id)->assertOk();
        $this->assertDatabaseHas('lesson_prerequisites', ['lesson_id' => $second->id, 'prerequisite_lesson_id' => $first->id]);
    }

    public function test_quiz_prerequisite_blocks_practice_until_minimum_score_is_reached(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $course = Course::factory()->create(['id' => 'quiz-prereq-course']);
        $lesson = $course->lessons()->create(['slug' => 'quiz-lesson', 'title' => 'Quiz lesson', 'title_en' => 'Quiz lesson', 'position' => 1]);
        $first = $lesson->trainings()->create(['type' => 'quiz', 'title_ar' => 'الأول', 'title_en' => 'First quiz', 'status' => 'published', 'position' => 1]);
        $second = $lesson->trainings()->create(['type' => 'quiz', 'title_ar' => 'الثاني', 'title_en' => 'Second quiz', 'status' => 'published', 'position' => 2]);
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/trainings/'.$second->id.'/prerequisites', ['prerequisite_training_id' => $first->id, 'minimum_score' => 70])->assertCreated();
        Sanctum::actingAs($student);
        $this->getJson('/api/courses/'.$course->id.'/lessons/quiz-lesson/practice/'.$second->id)->assertForbidden();
        TrainingAttempt::create(['training_id' => $first->id, 'student_id' => $student->id, 'answers' => [], 'score' => 80, 'status' => 'passed', 'started_at' => now(), 'submitted_at' => now()]);
        $this->getJson('/api/courses/'.$course->id.'/lessons/quiz-lesson/practice/'.$second->id)->assertOk();
    }
}
