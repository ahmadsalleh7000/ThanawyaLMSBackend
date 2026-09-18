<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\MediaAsset;
use App\Models\StudentOnboardingProfile;
use App\Models\TeacherCourseAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentPlanningApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_planner_combines_tasks_goal_streak_deadlines_and_badges(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');
        $student = User::factory()->create(['role' => 'student']);
        StudentOnboardingProfile::create(['student_id' => $student->id, 'weekly_goal_minutes' => 120]);
        $course = $this->course();
        $first = $course->lessons()->create(['slug' => 'intro', 'title' => 'المقدمة', 'title_en' => 'Introduction', 'duration_minutes' => 30, 'position' => 1]);
        $course->lessons()->create(['slug' => 'practice', 'title' => 'تطبيق', 'title_en' => 'Practice', 'duration_minutes' => 20, 'position' => 2]);
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now(), 'status' => 'active']);
        $student->lessonCompletions()->create(['lesson_id' => $first->id, 'completed_at' => now()]);
        Assessment::create(['course_id' => $course->id, 'duration_minutes' => 20, 'passing_score' => 70, 'is_published' => true, 'available_from' => now()->subDay(), 'due_at' => now()->addDays(3)]);
        Sanctum::actingAs($student);

        $this->getJson('/api/student/planner')->assertOk()
            ->assertJsonPath('data.todayTasks.0.id', 'practice')
            ->assertJsonPath('data.weeklyGoal.completedMinutes', 30)
            ->assertJsonPath('data.weeklyGoal.percent', 25)
            ->assertJsonPath('data.streak.current', 1)
            ->assertJsonPath('data.deadlines.0.courseId', $course->id)
            ->assertJsonPath('data.achievements.0.earned', true)
            ->assertJsonPath('data.leaderboard.enabled', false);

        Carbon::setTestNow();
    }

    public function test_leaderboard_is_opt_in_and_uses_a_privacy_safe_name(): void
    {
        $student = User::factory()->create(['name' => 'Ahmed Hassan', 'role' => 'student']);
        StudentOnboardingProfile::create(['student_id' => $student->id, 'leaderboard_opt_in' => false]);
        Sanctum::actingAs($student);

        $this->putJson('/api/student/planner/leaderboard', ['enabled' => true])
            ->assertOk()->assertJsonPath('data.enabled', true);
        $this->getJson('/api/student/planner')->assertOk()
            ->assertJsonPath('data.leaderboard.enabled', true)
            ->assertJsonPath('data.leaderboard.entries.0.name', 'Ahmed H.');
    }

    public function test_student_calendar_returns_only_accessible_learning_events(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course();
        $lesson = $course->lessons()->create(['slug' => 'scheduled', 'title' => 'درس مجدول', 'title_en' => 'Scheduled lesson', 'available_from' => now()->addDay(), 'position' => 1]);
        Assignment::create(['lesson_id' => $lesson->id, 'title' => 'مهمة', 'title_en' => 'Assignment', 'due_at' => now()->addDays(2), 'is_published' => true, 'position' => 1]);
        Assessment::create(['course_id' => $course->id, 'duration_minutes' => 20, 'passing_score' => 70, 'is_published' => true, 'due_at' => now()->addDays(3)]);
        $teacher = User::factory()->create(['role' => 'teacher']);
        LiveSession::create(['course_id' => $course->id, 'created_by' => $teacher->id, 'title' => 'جلسة مباشرة', 'title_en' => 'Live class', 'starts_at' => now()->addDays(4), 'meeting_url' => 'https://meet.google.com/abc-defg-hij', 'status' => 'published']);
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now(), 'status' => 'active']);
        Sanctum::actingAs($student);

        $this->getJson('/api/student/calendar')->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.type', 'lesson')
            ->assertJsonPath('data.1.type', 'assignment')
            ->assertJsonPath('data.2.type', 'assessment');
        $this->assertSame('live_session', $this->getJson('/api/student/calendar')->json('data.3.type'));

        Carbon::setTestNow();
    }

    public function test_assigned_teacher_can_create_only_approved_live_session_links(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = $this->course();
        TeacherCourseAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $course->id]);
        Sanctum::actingAs($teacher);
        $payload = ['title' => 'Live class', 'starts_at' => now()->addDay()->toISOString(), 'meeting_url' => 'https://example.com/room'];
        $this->postJson('/api/teacher/courses/'.$course->id.'/sessions', $payload)->assertUnprocessable();
        $session = $this->postJson('/api/teacher/courses/'.$course->id.'/sessions', [...$payload, 'meeting_url' => 'https://zoom.us/j/123'])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data');
        $lesson = $course->lessons()->create(['slug' => 'recording-lesson', 'title' => 'درس', 'title_en' => 'Lesson', 'position' => 1]);
        $media = MediaAsset::create(['lesson_id' => $lesson->id, 'kind' => 'video', 'status' => 'published', 'disk' => 'local', 'path' => 'recordings/class.mp4', 'original_name' => 'class.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 100]);
        $this->patchJson('/api/teacher/courses/'.$course->id.'/sessions/'.$session['id'].'/recording', ['media_asset_id' => $media->id])->assertOk()->assertJsonPath('data.recordingMediaId', $media->id);
    }

    public function test_live_session_reminders_are_idempotent_for_active_learners(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course();
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now(), 'status' => 'active']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $session = LiveSession::create(['course_id' => $course->id, 'created_by' => $teacher->id, 'title' => 'Live class', 'starts_at' => now()->addDay(), 'meeting_url' => 'https://zoom.us/j/123', 'status' => 'published']);

        $this->artisan('sessions:send-reminders')->assertExitCode(0);
        $this->artisan('sessions:send-reminders')->assertExitCode(0);
        $this->assertDatabaseCount('user_notifications', 1);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $student->id, 'type' => 'live_session_reminder']);
        $this->assertNotNull($session->id);
        Carbon::setTestNow();
    }

    public function test_only_assigned_teacher_can_mark_attendance_and_guardian_can_read_linked_status(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $parent = User::factory()->create(['role' => 'parent']);
        $course = $this->course();
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now(), 'status' => 'active']);
        $parent->linkedStudents()->attach($student->id, ['status' => 'accepted', 'linked_at' => now(), 'accepted_at' => now()]);
        $session = LiveSession::create(['course_id' => $course->id, 'created_by' => $teacher->id, 'title' => 'Live class', 'starts_at' => now()->addDay(), 'meeting_url' => 'https://zoom.us/j/123', 'status' => 'published']);
        TeacherCourseAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $course->id]);
        Sanctum::actingAs($student);
        $this->postJson('/api/teacher/courses/'.$course->id.'/sessions/'.$session->id.'/attendance', ['student_id' => $student->id, 'status' => 'present'])->assertForbidden();
        Sanctum::actingAs($teacher);
        $this->postJson('/api/teacher/courses/'.$course->id.'/sessions/'.$session->id.'/attendance', ['student_id' => $student->id, 'status' => 'present'])->assertOk();
        Sanctum::actingAs($parent);
        $this->getJson('/api/parent/students/'.$student->id.'/attendance')->assertOk()->assertJsonPath('data.0.status', 'present');
    }

    public function test_assessment_schedule_is_enforced(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course();
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now(), 'status' => 'active']);
        $assessment = Assessment::create(['course_id' => $course->id, 'duration_minutes' => 20, 'passing_score' => 70, 'is_published' => true, 'available_from' => now()->addDay()]);
        Sanctum::actingAs($student);

        $this->getJson("/api/courses/{$course->id}/assessment")->assertForbidden();
        $assessment->update(['available_from' => now()->subDays(2), 'due_at' => now()->subDay()]);
        $this->getJson("/api/courses/{$course->id}/assessment")->assertStatus(410);
    }

    private function course(): Course
    {
        return Course::create(['id' => 'planner-course', 'title' => 'برمجة', 'title_en' => 'Programming', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
    }
}
