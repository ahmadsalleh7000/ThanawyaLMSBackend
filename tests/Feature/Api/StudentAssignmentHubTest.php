<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentAssignmentHubTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $course = Course::factory()->create(['scope' => 'academic', 'is_published' => true, 'archived_at' => null]);
        $lesson = $course->lessons()->create(['slug' => 'intro', 'title' => 'مقدمة', 'title_en' => 'Introduction', 'position' => 1, 'status' => 'published']);
        $student = User::factory()->create(['role' => 'student']);
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        Sanctum::actingAs($student);

        return [$course, $lesson, $student];
    }

    public function test_hub_lists_published_assignments_with_own_results_summary_and_deep_links(): void
    {
        [$course, $lesson, $student] = $this->fixtures();
        $lesson->assignments()->create(['title' => 'جاهزة', 'title_en' => 'Ready task', 'is_published' => true]);
        $lesson->assignments()->create(['title' => 'متأخرة', 'is_published' => true, 'due_at' => now()->subDay()]);
        $lesson->assignments()->create(['title' => 'مسودة سرية', 'is_published' => false]);
        $graded = $lesson->assignments()->create(['title' => 'مصححة', 'is_published' => true]);
        $graded->submissions()->create(['user_id' => $student->id, 'body' => 'Private answer', 'status' => 'graded', 'score' => 0, 'feedback' => 'Add an example.', 'file_path' => 'secret/path', 'submitted_at' => now()]);
        $graded->submissions()->create(['user_id' => User::factory()->create()->id, 'status' => 'graded', 'score' => 100, 'feedback' => 'Other feedback', 'submitted_at' => now()]);
        $pending = $lesson->assignments()->create(['title' => 'بانتظار التصحيح', 'is_published' => true]);
        $pending->submissions()->create(['user_id' => $student->id, 'status' => 'submitted', 'submitted_at' => now()]);
        $response = $this->getJson('/api/me/assignments')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.summary.toDo', 2)->assertJsonPath('meta.summary.awaitingReview', 1)->assertJsonPath('meta.summary.graded', 1)->assertJsonPath('meta.summary.averageScore', 0)
            ->assertDontSee('مسودة سرية')->assertDontSee('secret/path')->assertDontSee('Other feedback')->assertDontSee('Private answer');
        $row = collect($response->json('data'))->firstWhere('id', $graded->id);
        $this->assertSame(0, $row['score']);
        $this->assertSame('Add an example.', $row['feedback']);
        $this->assertFalse($row['canSubmit']);
        $this->assertTrue($row['canOpen']);
        $this->assertSame('/course-player/'.$course->id.'?lesson=intro&tab=assignments&assignment='.$graded->id, $row['url']);
    }

    public function test_filters_pagination_and_validation_work_without_changing_summary(): void
    {
        [$course, $lesson] = $this->fixtures();
        foreach (range(1, 14) as $number) {
            $lesson->assignments()->create(['title' => 'مهمة '.$number, 'title_en' => 'Task '.$number, 'is_published' => true]);
        }
        $this->getJson('/api/me/assignments?page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 14)->assertJsonPath('meta.lastPage', 2);
        $this->getJson('/api/me/assignments?search=Task%2014&status=ready&course_id='.$course->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.summary.toDo', 14);
        $this->getJson('/api/me/assignments?status=returned')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/me/assignments?status=invalid')->assertUnprocessable();
        $this->getJson('/api/me/assignments?page=-1')->assertUnprocessable();
        $this->getJson('/api/me/assignments?course_id=unknown')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_own_results_remain_visible_after_access_expires_or_content_is_hidden(): void
    {
        [$course, $lesson, $student] = $this->fixtures();
        $assignment = $lesson->assignments()->create(['title' => 'Own task', 'is_published' => true]);
        $assignment->submissions()->create(['user_id' => $student->id, 'status' => 'returned', 'feedback' => 'Revise this.', 'submitted_at' => now()]);
        $lesson->assignments()->create(['title' => 'Untouched task', 'is_published' => true]);
        $student->enrollments()->update(['status' => 'expired']);
        $course->update(['is_published' => false]);
        $assignment->update(['is_published' => false]);
        $this->getJson('/api/me/assignments')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.canOpen', false)->assertJsonPath('data.0.canSubmit', false)->assertJsonPath('data.0.feedback', 'Revise this.')->assertJsonPath('meta.summary.toDo', 0);
    }

    public function test_student_cannot_see_unrelated_course_work_or_other_students_results(): void
    {
        [, $lesson] = $this->fixtures();
        $assignment = $lesson->assignments()->create(['title' => 'Private task', 'is_published' => false]);
        $assignment->submissions()->create(['user_id' => User::factory()->create()->id, 'status' => 'graded', 'score' => 100, 'feedback' => 'Private feedback', 'submitted_at' => now()]);
        $other = Course::factory()->create(['scope' => 'academic', 'is_published' => true]);
        $otherLesson = $other->lessons()->create(['slug' => 'other', 'title' => 'Other', 'title_en' => 'Other', 'position' => 1, 'status' => 'published']);
        $otherLesson->assignments()->create(['title' => 'Unrelated task', 'is_published' => true]);
        $this->getJson('/api/me/assignments')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_locked_lessons_cannot_be_opened_and_non_students_are_denied(): void
    {
        [, $lesson] = $this->fixtures();
        $lesson->assignments()->create(['title' => 'Upcoming lesson task', 'is_published' => true]);
        $lesson->update(['available_from' => now()->addDay()]);
        $this->getJson('/api/me/assignments')->assertOk()->assertJsonPath('data.0.canOpen', false)->assertJsonPath('meta.summary.toDo', 0);
        foreach (['teacher', 'parent', 'admin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->getJson('/api/me/assignments')->assertForbidden();
        }
    }
}
