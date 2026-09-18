<?php

namespace Tests\Feature\Api;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\TeacherCourseAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_can_read_and_submit_private_assignment_content(): void
    {
        Storage::fake('local');
        [$course, $assignment] = $this->assignment();
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        Sanctum::actingAs($student);

        $this->getJson($this->url($course, $assignment))->assertOk()->assertJsonPath('data.status', 'ready');
        $response = $this->post($this->url($course, $assignment).'/submissions', [
            'body' => 'My completed work',
            'file' => UploadedFile::fake()->create('answer.pdf', 20, 'application/pdf'),
        ]);
        $response->assertOk()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.submission.fileName', 'answer.pdf');
        $this->assertDatabaseHas('assignment_submissions', ['assignment_id' => $assignment->id, 'user_id' => $student->id, 'file_original_name' => 'answer.pdf']);
        $this->assertCount(1, Storage::disk('local')->allFiles('assignment-submissions'));
    }

    public function test_assignment_access_requires_enrollment_and_rejects_unsafe_uploads(): void
    {
        Storage::fake('local');
        [$course, $assignment] = $this->assignment();
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student);
        $this->getJson($this->url($course, $assignment))->assertForbidden();
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        $this->post($this->url($course, $assignment).'/submissions', ['file' => UploadedFile::fake()->create('script.php', 10, 'text/plain')])->assertUnprocessable();
        $assignment->update(['is_published' => false]);
        $this->getJson($this->url($course, $assignment))->assertNotFound();
    }

    public function test_assigned_teacher_can_grade_submissions_only_in_their_course(): void
    {
        [$course, $assignment] = $this->assignment();
        $otherCourse = Course::create(['id' => 'other-assignment-api-course', 'title' => 'أخرى', 'title_en' => 'Other', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'scope' => 'academic', 'level' => 'Beginner', 'duration_minutes' => 30, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
        $otherLesson = $otherCourse->lessons()->create(['slug' => 'other-lesson', 'title' => 'درس', 'title_en' => 'Lesson', 'duration_minutes' => 10, 'position' => 1, 'status' => 'published']);
        $otherAssignment = $otherLesson->assignments()->create(['title' => 'مهمة أخرى', 'title_en' => 'Other task', 'is_published' => true, 'position' => 1]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $submission = AssignmentSubmission::create(['assignment_id' => $assignment->id, 'user_id' => $student->id, 'body' => 'Work', 'status' => 'submitted', 'submitted_at' => now()]);
        $otherSubmission = AssignmentSubmission::create(['assignment_id' => $otherAssignment->id, 'user_id' => $student->id, 'body' => 'Other work', 'status' => 'submitted', 'submitted_at' => now()]);
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        TeacherCourseAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $course->id]);
        Sanctum::actingAs($teacher);

        $this->getJson("/api/teacher/courses/{$course->id}/assignments/submissions")->assertOk()->assertJsonPath('data.0.id', $submission->id);
        $this->patchJson("/api/teacher/courses/{$course->id}/assignments/submissions/{$submission->id}/grade", ['score' => 84, 'feedback' => 'Good progress.'])
            ->assertOk()->assertJsonPath('data.status', 'graded')->assertJsonPath('data.score', 84);
        $this->assertDatabaseHas('audit_logs', ['action' => 'teacher.assignment_submission.graded', 'user_id' => $teacher->id]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $student->id, 'type' => 'assignment_graded']);
        Storage::disk('local')->put('assignment-submissions/test.pdf', 'file');
        $submission->update(['file_path' => 'assignment-submissions/test.pdf', 'file_original_name' => 'work.pdf']);
        $this->get("/api/teacher/courses/{$course->id}/assignments/submissions/{$submission->id}/file")
            ->assertOk()->assertHeader('content-disposition', 'attachment; filename=work.pdf');
        $this->patchJson("/api/teacher/courses/{$course->id}/assignments/submissions/{$submission->id}/return", ['feedback' => 'Please revise the conclusion.'])
            ->assertOk()->assertJsonPath('data.status', 'returned')->assertJsonPath('data.score', null);
        $this->assertDatabaseHas('audit_logs', ['action' => 'teacher.assignment_submission.returned', 'user_id' => $teacher->id]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $student->id, 'type' => 'assignment_returned']);
        $this->patchJson("/api/teacher/courses/{$course->id}/assignments/submissions/{$otherSubmission->id}/grade", ['score' => 50])->assertNotFound();
        $this->get("/api/teacher/courses/{$otherCourse->id}/assignments/submissions/{$submission->id}/file")->assertForbidden();
    }

    public function test_guardian_sees_assignment_status_only_for_a_linked_student(): void
    {
        [$course, $assignment] = $this->assignment();
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $otherStudent = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        AssignmentSubmission::create(['assignment_id' => $assignment->id, 'user_id' => $student->id, 'status' => 'graded', 'score' => 92, 'submitted_at' => now()]);
        $parent = User::factory()->create(['role' => User::ROLE_PARENT]);
        $parent->linkedStudents()->attach($student->id, ['status' => 'accepted', 'linked_at' => now(), 'accepted_at' => now()]);
        Sanctum::actingAs($parent);

        $this->getJson("/api/parent/students/{$student->id}/assignments")->assertOk()->assertJsonPath('data.0.status', 'graded')->assertJsonPath('data.0.score', 92);
        $this->getJson("/api/parent/students/{$otherStudent->id}/assignments")->assertNotFound();
    }

    public function test_student_resubmission_after_return_is_marked_resubmitted(): void
    {
        [$course, $assignment] = $this->assignment();
        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'is_active' => true]);
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        $submission = AssignmentSubmission::create(['assignment_id' => $assignment->id, 'user_id' => $student->id, 'body' => 'Old work', 'status' => 'returned', 'submitted_at' => now()]);
        $this->actingAs($student)->postJson($this->url($course, $assignment).'/submissions', ['body' => 'Revised work'])
            ->assertOk()->assertJsonPath('data.status', 'resubmitted');
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id, 'status' => 'resubmitted', 'body' => 'Revised work']);
    }

    private function assignment(): array
    {
        $course = Course::create(['id' => 'assignment-api-course', 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'scope' => 'academic', 'level' => 'Beginner', 'duration_minutes' => 30, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
        $lesson = $course->lessons()->create(['slug' => 'assignment-lesson', 'title' => 'درس', 'title_en' => 'Lesson', 'duration_minutes' => 10, 'position' => 1, 'status' => 'published']);

        return [$course, $lesson->assignments()->create(['title' => 'مهمة', 'title_en' => 'Assignment', 'description_en' => 'Submit work.', 'is_published' => true, 'position' => 1])];
    }

    private function url(Course $course, Assignment $assignment): string
    {
        return "/api/courses/{$course->id}/lessons/assignment-lesson/assignments/{$assignment->id}";
    }
}
