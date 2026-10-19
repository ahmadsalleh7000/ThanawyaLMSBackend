<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonTrainingStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_shows_saved_training_score_for_bearer_authenticated_student(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $course = Course::factory()->create();
        $lesson = $course->lessons()->create([
            'slug' => 'training-lesson', 'title' => 'Training lesson',
            'title_en' => 'Training lesson', 'position' => 1,
        ]);
        $training = $lesson->trainings()->create([
            'type' => 'quiz', 'title_ar' => 'Training', 'title_en' => 'Training',
            'status' => 'published', 'position' => 1, 'passing_score' => 70,
        ]);
        $question = $training->questions()->create([
            'topic' => 'Basics', 'topic_ar' => 'Basics',
            'prompt' => 'Choose the correct answer', 'prompt_ar' => 'Choose the correct answer',
            'options' => ['Correct', 'Wrong'], 'options_ar' => ['Correct', 'Wrong'],
            'correct_option' => 0, 'explanation' => 'Correct.', 'explanation_ar' => 'Correct.',
        ]);
        $student->enrollments()->create([
            'course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now(),
        ]);
        $this->withToken($student->createToken('training-test')->plainTextToken);

        $this->getJson('/api/courses/'.$course->id)->assertOk()
            ->assertJsonPath('data.lessons.0.trainings.0.bestAttempt', null);
        $this->postJson('/api/courses/'.$course->id.'/lessons/'.$lesson->slug.'/practice/'.$training->id, [
            'answers' => [$question->id => 0],
        ])->assertOk()->assertJsonPath('data.result.passed', true);

        // Public course requests must resolve the bearer token without auth middleware.
        app('auth')->forgetGuards();
        $this->getJson('/api/courses/'.$course->id)->assertOk()
            ->assertJsonPath('data.lessons.0.trainings.0.bestAttempt.score', 100)
            ->assertJsonPath('data.lessons.0.trainings.0.bestAttempt.passed', true)
            ->assertJsonPath('data.lessons.0.learningState.practicePassed', true);

        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $other->enrollments()->create([
            'course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now(),
        ]);
        app('auth')->forgetGuards();
        $this->withToken($other->createToken('other-training-test')->plainTextToken)
            ->getJson('/api/courses/'.$course->id)->assertOk()
            ->assertJsonPath('data.lessons.0.trainings.0.bestAttempt', null);
    }
}
