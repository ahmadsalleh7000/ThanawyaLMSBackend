<?php

namespace Tests\Feature\Api;

use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentRecommendationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_receives_only_own_weak_topic_summary(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $course = Course::factory()->create(['id' => 'recommendation-course']);
        $course->lessons()->create(['slug' => 'recommendation-lesson', 'title' => 'Lesson', 'title_en' => 'Lesson', 'position' => 1]);
        $assessment = $course->assessment()->create(['duration_minutes' => 20, 'passing_score' => 70, 'is_published' => true]);
        $question = $assessment->questions()->create(['topic' => 'Fractions', 'topic_ar' => 'الكسور', 'prompt' => '2 + 2?', 'prompt_ar' => '؟', 'type' => 'multiple_choice', 'options' => ['3', '4'], 'options_ar' => ['٣', '٤'], 'correct_option' => 1, 'explanation' => 'The answer is four.', 'explanation_ar' => 'الإجابة أربعة.', 'position' => 1]);
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        AssessmentAttempt::create(['assessment_id' => $assessment->id, 'user_id' => $student->id, 'answers' => [$question->id => 0], 'score' => 0, 'passed' => false, 'submitted_at' => now()]);
        Sanctum::actingAs($student);
        $this->getJson('/api/me/recommendations/weak-topics')->assertOk()->assertJsonPath('data.0.topic', 'Fractions')->assertJsonPath('data.0.score', 0)->assertJsonMissingPath('data.0.answers');
    }

    public function test_recommendations_are_student_only(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PARENT]);
        Sanctum::actingAs($user);
        $this->getJson('/api/me/recommendations/weak-topics')->assertForbidden();
    }
}
