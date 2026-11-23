<?php

namespace Tests\Feature\Web;

use App\Models\Course;
use App\Models\User;
use App\Services\AssignmentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor']));
        $course = Course::factory()->create(['is_published' => true, 'archived_at' => null]);
        $lesson = $course->lessons()->create(['slug' => 'assignment-intro', 'title' => 'مقدمة', 'title_en' => 'Introduction', 'position' => 1, 'status' => 'published']);
        $assignment = $lesson->assignments()->create(['title' => 'مهمة البرمجة', 'title_en' => 'Programming task', 'is_published' => false, 'position' => 1]);

        return [$course, $lesson, $assignment];
    }

    public function test_staff_can_create_edit_publish_filter_and_delete_empty_drafts(): void
    {
        [$course, $lesson, $assignment] = $this->fixtures();
        $this->get(route('admin.assignments.index'))->assertOk()->assertSee('مهمة البرمجة')->assertSee('مهام الدروس');
        $this->get(route('admin.lessons.edit', [$course, $lesson]))->assertOk()->assertSee(route('admin.assignments.index', ['lesson_id' => $lesson->id]), false);
        $this->get(route('admin.assignments.create'))->assertOk()->assertSee('اختر الدورة');
        $this->get(route('admin.assignments.create', ['lesson_id' => $lesson->id]))->assertOk()->assertSee('مقدمة');
        $this->post(route('admin.assignments.store'), [
            'lesson_id' => $lesson->id, 'title' => 'مهمة جديدة', 'title_en' => 'New task',
            'description' => 'تعليمات المهمة', 'due_at' => '2026-11-01T10:00', 'is_published' => true, 'position' => 2,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $new = $lesson->assignments()->where('title_en', 'New task')->firstOrFail();
        $this->assertTrue($new->is_published);
        $this->assertSame('2026-11-01 10:00', $new->due_at->format('Y-m-d H:i'));
        $this->get(route('admin.assignments.edit', $new))->assertOk()->assertSee('New task');
        $this->put(route('admin.assignments.update', $new), ['title' => 'معدلة', 'is_published' => false, 'due_at' => null, 'position' => 3])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($new->fresh()->is_published);
        $this->assertNull($new->fresh()->due_at);
        $this->get(route('admin.assignments.index', ['search' => 'معدلة', 'status' => 'draft', 'course_id' => $course->id, 'lesson_id' => $lesson->id]))->assertOk()->assertViewHas('assignments', fn ($records) => $records->total() === 1);
        $this->get(route('admin.assignments.index', ['status' => 'published']))->assertOk()->assertViewHas('assignments', fn ($records) => $records->total() === 0);
        $this->delete(route('admin.assignments.destroy', $assignment))->assertRedirect(route('admin.assignments.index'));
        $this->assertDatabaseMissing('assignments', ['id' => $assignment->id]);
        foreach (['created', 'updated', 'deleted'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => 'admin.assignment.'.$action]);
        }
    }

    public function test_submissions_can_be_reviewed_returned_filtered_and_downloaded_privately(): void
    {
        Storage::fake('local');
        [, $lesson, $assignment] = $this->fixtures();
        $student = User::factory()->create(['name' => 'Assignment Learner']);
        Storage::disk('local')->put('assignment-submissions/work.txt', 'Private work');
        $submission = $assignment->submissions()->create([
            'user_id' => $student->id, 'body' => '<script>unsafe()</script>', 'status' => 'submitted', 'submitted_at' => now(),
            'file_path' => 'assignment-submissions/work.txt', 'file_original_name' => 'work.txt',
        ]);
        $this->get(route('admin.assignments.submissions', $assignment))->assertOk()->assertSee('Assignment Learner')->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)->assertDontSee('<script>unsafe()</script>', false);
        $this->get(route('admin.assignments.file', [$assignment, $submission]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->patch(route('admin.assignments.review', [$assignment, $submission]), ['action' => 'grade', 'score' => 0, 'feedback' => 'Try again.'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id, 'status' => 'graded', 'score' => 0]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $student->id, 'type' => 'assignment_graded', 'action_url' => '/course-player/'.$lesson->course_id.'?lesson='.$lesson->slug.'&tab=assignments']);
        $this->patch(route('admin.assignments.review', [$assignment, $submission]), ['action' => 'return', 'feedback' => 'Revise the answer.'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id, 'status' => 'returned', 'score' => null]);
        $this->get(route('admin.assignments.submissions', ['assignment' => $assignment, 'status' => 'graded']))->assertOk()->assertViewHas('submissions', fn ($records) => $records->total() === 0);
        $this->get(route('admin.assignments.submissions', ['assignment' => $assignment, 'search' => 'Assignment Learner', 'status' => 'returned']))->assertOk()->assertViewHas('submissions', fn ($records) => $records->total() === 1);
        $other = $lesson->assignments()->create(['title' => 'Other', 'position' => 2]);
        $this->get(route('admin.assignments.file', [$other, $submission]))->assertNotFound();
        $this->patch(route('admin.assignments.review', [$other, $submission]), ['action' => 'grade', 'score' => 100])->assertNotFound();
        $this->delete(route('admin.assignments.destroy', $assignment))->assertConflict();
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id]);
    }

    public function test_validation_archive_guards_pagination_and_stale_review_protection(): void
    {
        [$course, $lesson, $assignment] = $this->fixtures();
        $this->post(route('admin.assignments.store'), ['title' => 'Missing lesson'])->assertSessionHasErrors('lesson_id');
        $this->put(route('admin.assignments.update', $assignment), ['title' => '', 'position' => 0, 'due_at' => 'bad'])->assertSessionHasErrors(['title', 'position', 'due_at']);
        $assignment->update(['is_published' => true]);
        $this->delete(route('admin.assignments.destroy', $assignment))->assertConflict();
        $submission = $assignment->submissions()->create(['user_id' => User::factory()->create()->id, 'status' => 'submitted', 'submitted_at' => now()]);
        $reviewUrl = route('admin.assignments.review', [$assignment, $submission]);
        $this->patch($reviewUrl, ['action' => 'return', 'feedback' => ' '])->assertSessionHasErrors('feedback');
        $this->patch($reviewUrl, ['action' => 'grade', 'score' => 101])->assertSessionHasErrors('score');
        $this->patch($reviewUrl, ['action' => 'grade', 'score' => 90, 'submitted_at' => now()->subDay()->toISOString()])->assertSessionHasErrors('score');
        $this->assertSame('submitted', $submission->fresh()->status);
        $version = app(AssignmentWorkflowService::class)->reviewVersion($submission);
        $submission->update(['body' => 'A newer answer in the same second']);
        $this->patch($reviewUrl, ['action' => 'grade', 'score' => 90, 'review_version' => $version])->assertSessionHasErrors('score');
        $this->assertSame('submitted', $submission->fresh()->status);
        foreach (range(1, 22) as $number) {
            $lesson->assignments()->create(['title' => 'Task '.$number]);
        }
        $this->get(route('admin.assignments.index', ['lesson_id' => $lesson->id, 'page' => 2]))->assertOk()->assertViewHas('assignments', fn ($records) => $records->total() === 23 && $records->count() === 3);
        $course->update(['archived_at' => now()]);
        $this->put(route('admin.assignments.update', $assignment), ['title' => 'Changed'])->assertConflict();
        $this->post(route('admin.assignments.store'), ['lesson_id' => $lesson->id, 'title' => 'Changed'])->assertConflict();
    }

    public function test_students_teachers_and_non_content_staff_cannot_manage_assignments(): void
    {
        [, $lesson, $assignment] = $this->fixtures();
        $submission = $assignment->submissions()->create(['user_id' => User::factory()->create()->id, 'submitted_at' => now()]);
        foreach ([['student', null], ['teacher', null], ['admin', 'finance_reviewer']] as [$role, $adminRole]) {
            $this->actingAs(User::factory()->create(['role' => $role, 'admin_role' => $adminRole]));
            foreach (['index', 'create', 'edit', 'submissions'] as $action) {
                $this->get(route('admin.assignments.'.$action, in_array($action, ['edit', 'submissions']) ? $assignment : []))->assertForbidden();
            }
            $this->post(route('admin.assignments.store'), ['lesson_id' => $lesson->id, 'title' => 'Blocked'])->assertForbidden();
            $this->put(route('admin.assignments.update', $assignment), ['title' => 'Blocked'])->assertForbidden();
            $this->delete(route('admin.assignments.destroy', $assignment))->assertForbidden();
            $this->patch(route('admin.assignments.review', [$assignment, $submission]), ['action' => 'grade', 'score' => 100])->assertForbidden();
            $this->get(route('admin.assignments.file', [$assignment, $submission]))->assertForbidden();
        }
    }
}
