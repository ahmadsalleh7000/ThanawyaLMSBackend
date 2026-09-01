<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssessmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_answers_are_scored_on_server_and_issue_certificate_only_when_eligible(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $course = Course::create(['id' => 'secure-1', 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
        $lesson = $course->lessons()->create(['slug' => 'lesson-1', 'title' => 'درس', 'title_en' => 'Lesson', 'duration_minutes' => 10, 'position' => 1]);
        $assessment = Assessment::create(['course_id' => $course->id, 'passing_score' => 70, 'is_published' => true]);
        $question = $assessment->questions()->create(['topic' => 'Basics', 'topic_ar' => 'أساسيات', 'prompt' => 'Correct?', 'prompt_ar' => 'صحيح؟', 'options' => ['Yes', 'No'], 'correct_option' => 0, 'explanation' => 'Yes.', 'explanation_ar' => 'نعم.', 'position' => 1]);
        $user->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);

        $this->postJson("/api/courses/{$course->id}/assessment/submit", ['answers' => [$question->id => 0]])
            ->assertOk()->assertJsonPath('score', 100)->assertJsonPath('certificateId', null);

        $user->lessonCompletions()->create(['lesson_id' => $lesson->id, 'completed_at' => now()]);
        $response = $this->postJson("/api/courses/{$course->id}/assessment/submit", ['answers' => [$question->id => 0]])
            ->assertOk()->assertJsonPath('passed', true);
        $this->assertNotNull($response->json('certificateId'));
        $this->getJson('/api/certificates/verify/'.$response->json('certificateId'))->assertOk()->assertJsonPath('valid', true)->assertJsonPath('student', $user->name);
        $this->getJson('/api/me/certificates')->assertOk()
            ->assertJsonPath('data.0.id', $response->json('certificateId'))
            ->assertJsonPath('data.0.courseId', $course->id);
    }

    public function test_assessment_payload_never_exposes_correct_answers(): void
    {
        $this->seed();
        $user = User::first();
        Sanctum::actingAs($user);
        $user->enrollments()->firstOrCreate(['course_id' => 'python-1'], ['enrolled_at' => now()]);
        $this->getJson('/api/courses/python-1/assessment')->assertOk()->assertJsonMissing(['correctOption' => 0]);
    }

    public function test_random_question_subset_is_stable_and_only_that_subset_is_graded(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($user);
        $course = Course::factory()->create(['is_published' => true]);
        $assessment = Assessment::create(['course_id' => $course->id, 'passing_score' => 50, 'randomize_questions' => true, 'question_limit' => 2, 'is_published' => true]);
        foreach (range(1, 4) as $position) {
            $assessment->questions()->create(['topic' => 'Topic', 'topic_ar' => 'موضوع', 'prompt' => 'Question '.$position, 'prompt_ar' => 'سؤال '.$position, 'options' => ['Yes', 'No'], 'options_ar' => ['نعم', 'لا'], 'correct_option' => 0, 'explanation' => 'Yes', 'explanation_ar' => 'نعم', 'position' => $position]);
        }
        $user->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);

        $first = $this->getJson('/api/courses/'.$course->id.'/assessment')->assertOk()->json('questions');
        $second = $this->getJson('/api/courses/'.$course->id.'/assessment')->assertOk()->json('questions');
        $this->assertCount(2, $first);
        $this->assertSame(array_column($first, 'id'), array_column($second, 'id'));

        $answers = collect($first)->mapWithKeys(fn ($question) => [$question['id'] => 0])->all();
        $this->postJson('/api/courses/'.$course->id.'/assessment/submit', ['answers' => $answers])
            ->assertOk()->assertJsonPath('total', 2)->assertJsonPath('score', 100);
    }

    public function test_student_can_start_save_and_resume_a_private_assessment_draft(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true]);
        $assessment = Assessment::create(['course_id' => $course->id, 'duration_minutes' => 20, 'passing_score' => 70, 'is_published' => true]);
        $question = $assessment->questions()->create(['topic' => 'Topic', 'topic_ar' => 'موضوع', 'prompt' => 'Question', 'prompt_ar' => 'سؤال', 'options' => ['Yes', 'No'], 'options_ar' => ['نعم', 'لا'], 'correct_option' => 0, 'explanation' => 'Yes', 'explanation_ar' => 'نعم', 'position' => 1]);
        $user->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);
        $other->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);

        Sanctum::actingAs($user);
        $this->postJson("/api/courses/{$course->id}/assessment/start")->assertOk()->assertJsonPath('data.currentIndex', 0);
        $this->putJson("/api/courses/{$course->id}/assessment/draft", ['answers' => [$question->id => 1], 'current_index' => 0])
            ->assertOk()->assertJsonPath('data.answers.'.$question->id, 1);
        $this->getJson("/api/courses/{$course->id}/assessment")->assertOk()->assertJsonPath('draft.answers.'.$question->id, 1);

        Sanctum::actingAs($other);
        $this->getJson("/api/courses/{$course->id}/assessment")->assertOk()->assertJsonPath('draft', null);
    }

    public function test_expired_assessment_uses_the_last_server_saved_answers(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($user);
        $course = Course::factory()->create(['is_published' => true]);
        $assessment = Assessment::create(['course_id' => $course->id, 'duration_minutes' => 20, 'passing_score' => 70, 'is_published' => true]);
        $question = $assessment->questions()->create(['topic' => 'Topic', 'topic_ar' => 'موضوع', 'prompt' => 'Question', 'prompt_ar' => 'سؤال', 'options' => ['Yes', 'No'], 'options_ar' => ['نعم', 'لا'], 'correct_option' => 0, 'explanation' => 'Yes', 'explanation_ar' => 'نعم', 'position' => 1]);
        $user->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);
        $draft = $assessment->drafts()->create(['user_id' => $user->id, 'answers' => [$question->id => 0], 'current_index' => 0, 'started_at' => now()->subMinutes(21), 'expires_at' => now()->subMinute()]);

        $this->postJson("/api/courses/{$course->id}/assessment/submit", ['answers' => [$question->id => 1]])
            ->assertOk()->assertJsonPath('score', 100);
        $this->assertDatabaseMissing('assessment_drafts', ['id' => $draft->id]);
    }
}
