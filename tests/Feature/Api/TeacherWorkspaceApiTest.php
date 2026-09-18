<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonComment;
use App\Models\TeacherCourseAssignment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherWorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_sees_only_active_assigned_courses(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $assigned = $this->course('assigned-course');
        $unassigned = $this->course('unassigned-course');
        TeacherCourseAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $assigned->id, 'is_active' => true]);
        TeacherCourseAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $unassigned->id, 'is_active' => false]);
        Sanctum::actingAs($teacher);

        $this->getJson('/api/teacher/courses')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.courseId', $assigned->id);
    }

    public function test_students_and_other_teachers_cannot_read_an_assignment(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $otherTeacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $course = $this->course('private-teacher-course');
        TeacherCourseAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $course->id]);

        Sanctum::actingAs($otherTeacher);
        $this->getJson('/api/teacher/courses')->assertOk()->assertJsonCount(0, 'data');
        Sanctum::actingAs($student);
        $this->getJson('/api/teacher/courses')->assertForbidden();
    }

    public function test_teacher_can_open_only_an_assigned_course_workspace(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $assigned = $this->course('workspace-course');
        $other = $this->course('other-workspace-course');
        $unit = Unit::create(['course_id' => $assigned->id, 'slug' => 'unit-one', 'title_ar' => 'الوحدة الأولى', 'title_en' => 'Unit one', 'position' => 1]);
        Lesson::create([
            'course_id' => $assigned->id,
            'unit_id' => $unit->id,
            'slug' => 'workspace-lesson',
            'title' => 'درس المعلم',
            'title_en' => 'Teacher lesson',
            'position' => 1,
            'status' => 'draft',
        ]);
        TeacherCourseAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $assigned->id]);
        Sanctum::actingAs($teacher);

        $this->getJson("/api/teacher/courses/{$assigned->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $assigned->id)
            ->assertJsonPath('data.lessons.0.titleEn', 'Teacher lesson');
        $this->getJson("/api/teacher/courses/{$other->id}")->assertForbidden();
    }

    public function test_teacher_can_read_assigned_questions_and_reply_with_audit(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $assigned = $this->course('question-course');
        $lesson = Lesson::factory()->for($assigned)->create(['slug' => 'question-lesson', 'comments_enabled' => true]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $question = LessonComment::create(['lesson_id' => $lesson->id, 'user_id' => $student->id, 'content' => 'How do I start?']);
        TeacherCourseAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $assigned->id]);
        Sanctum::actingAs($teacher);

        $this->getJson('/api/teacher/questions')
            ->assertOk()
            ->assertJsonPath('data.0.question.id', $question->id)
            ->assertJsonPath('data.0.lesson.slug', 'question-lesson');
        $this->postJson("/api/teacher/courses/{$assigned->id}/lessons/{$lesson->slug}/comments/{$question->id}/reply", ['content' => 'Start with the first example.'])
            ->assertCreated()
            ->assertJsonPath('data.author.role', 'teacher');
        $this->assertDatabaseHas('user_notifications', ['user_id' => $student->id, 'type' => 'lesson_comment', 'action_url' => "/course-player/{$assigned->id}?lesson={$lesson->slug}"]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'teacher.lesson_comment.replied', 'user_id' => $teacher->id]);
    }

    public function test_teacher_can_create_a_published_assignment_only_on_an_assigned_lesson(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $course = $this->course('assignment-course');
        $otherCourse = $this->course('other-assignment-course');
        $lesson = Lesson::factory()->for($course)->create(['slug' => 'assignment-lesson']);
        $otherLesson = Lesson::factory()->for($otherCourse)->create(['slug' => 'other-assignment-lesson']);
        TeacherCourseAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $course->id]);
        Sanctum::actingAs($teacher);

        $this->postJson("/api/teacher/courses/{$course->id}/lessons/{$lesson->slug}/assignments", [
            'title' => 'حل تدريبي', 'title_en' => 'Practice task', 'description_en' => 'Submit your work.', 'is_published' => true,
        ])->assertCreated()->assertJsonPath('data.titleEn', 'Practice task')->assertJsonPath('data.isPublished', true);
        $this->assertDatabaseHas('audit_logs', ['action' => 'teacher.assignment.created', 'user_id' => $teacher->id]);
        $this->postJson("/api/teacher/courses/{$otherCourse->id}/lessons/{$otherLesson->slug}/assignments", ['title' => 'Blocked'])->assertForbidden();
    }

    public function test_teacher_receives_assigned_course_outcomes_and_at_risk_summary(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $course = $this->course('outcomes-course');
        $assessment = Assessment::create(['course_id' => $course->id, 'passing_score' => 70, 'is_published' => true]);
        $passed = User::factory()->create(['role' => User::ROLE_STUDENT, 'name' => 'Passed Student']);
        $atRisk = User::factory()->create(['role' => User::ROLE_STUDENT, 'name' => 'At Risk Student']);
        Enrollment::create(['user_id' => $passed->id, 'course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        Enrollment::create(['user_id' => $atRisk->id, 'course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        AssessmentAttempt::create(['assessment_id' => $assessment->id, 'user_id' => $passed->id, 'answers' => [], 'score' => 90, 'passed' => true, 'submitted_at' => now()]);
        AssessmentAttempt::create(['assessment_id' => $assessment->id, 'user_id' => $atRisk->id, 'answers' => [], 'score' => 40, 'passed' => false, 'submitted_at' => now()]);
        TeacherCourseAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $course->id]);
        Sanctum::actingAs($teacher);

        $this->getJson("/api/teacher/courses/{$course->id}/outcomes")
            ->assertOk()
            ->assertJsonPath('data.summary.learners', 2)
            ->assertJsonPath('data.summary.passRate', 50)
            ->assertJsonPath('data.summary.atRisk', 1)
            ->assertJsonPath('data.learners.1.status', 'needs_attention');
    }

    public function test_teacher_receives_question_miss_rates_only_for_an_assigned_course(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $course = $this->course('analytics-course');
        $otherCourse = $this->course('other-analytics-course');
        $assessment = Assessment::create(['course_id' => $course->id, 'passing_score' => 70, 'is_published' => true]);
        $question = $assessment->questions()->create(['topic' => 'Basics', 'topic_ar' => 'أساسيات', 'prompt' => 'Pick A', 'prompt_ar' => 'اختر أ', 'options' => ['A', 'B'], 'options_ar' => ['أ', 'ب'], 'correct_option' => 0, 'explanation' => 'A', 'explanation_ar' => 'أ', 'position' => 1]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $secondStudent = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        $secondStudent->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        AssessmentAttempt::create(['assessment_id' => $assessment->id, 'user_id' => $student->id, 'answers' => [$question->id => 1], 'score' => 0, 'passed' => false, 'submitted_at' => now()]);
        AssessmentAttempt::create(['assessment_id' => $assessment->id, 'user_id' => $secondStudent->id, 'answers' => [$question->id => 1], 'score' => 0, 'passed' => false, 'submitted_at' => now()]);
        TeacherCourseAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $course->id]);
        Sanctum::actingAs($teacher);

        $analytics = $this->getJson("/api/teacher/courses/{$course->id}/analytics");
        $analytics
            ->assertOk()
            ->assertJsonPath('data.questions.0.missRate', 100)
            ->assertJsonPath('data.questions.0.incorrect', 2)
            ->assertJsonPath('data.questions.0.commonWrongOptions.0.option', 1)
            ->assertJsonPath('data.questions.0.commonWrongOptions.0.count', 2);
        $this->getJson("/api/teacher/courses/{$otherCourse->id}/analytics")->assertForbidden();
    }

    public function test_teacher_gradebook_contains_latest_active_learner_attempts_and_scoped_csv_export(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $course = $this->course('gradebook-course');
        $otherCourse = $this->course('other-gradebook-course');
        $assessment = Assessment::create(['course_id' => $course->id, 'passing_score' => 70, 'is_published' => true]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'name' => 'Gradebook Student']);
        $inactive = User::factory()->create(['role' => User::ROLE_STUDENT, 'name' => 'Inactive Student']);
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        $inactive->enrollments()->create(['course_id' => $course->id, 'status' => 'cancelled', 'enrolled_at' => now()]);
        $lesson = Lesson::factory()->for($course)->create(['slug' => 'gradebook-assignment-lesson']);
        $assignment = Assignment::create(['lesson_id' => $lesson->id, 'title' => 'Written reflection', 'title_en' => 'Written reflection', 'is_published' => true, 'position' => 1]);
        AssignmentSubmission::create(['assignment_id' => $assignment->id, 'user_id' => $student->id, 'body' => 'Reflection', 'status' => 'graded', 'score' => 85, 'submitted_at' => now()]);
        AssessmentAttempt::create(['assessment_id' => $assessment->id, 'user_id' => $student->id, 'answers' => [], 'score' => 40, 'passed' => false, 'submitted_at' => now()->subMinute()]);
        $latestAttempt = AssessmentAttempt::create(['assessment_id' => $assessment->id, 'user_id' => $student->id, 'answers' => [], 'score' => 90, 'passed' => true, 'submitted_at' => now()]);
        AssessmentAttempt::create(['assessment_id' => $assessment->id, 'user_id' => $inactive->id, 'answers' => [], 'score' => 100, 'passed' => true, 'submitted_at' => now()]);
        TeacherCourseAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $course->id]);
        Sanctum::actingAs($teacher);

        $this->getJson("/api/teacher/courses/{$course->id}/gradebook")
            ->assertOk()
            ->assertJsonPath('data.assessment.passingScore', 70)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.student.name', 'Gradebook Student')
            ->assertJsonPath('data.rows.0.attempts', 2)
            ->assertJsonPath('data.rows.0.score', 90)
            ->assertJsonPath('data.rows.0.passed', true)
            ->assertJsonPath('data.assignments.0.titleEn', 'Written reflection')
            ->assertJsonPath('data.rows.0.assignmentsSubmitted', 1)
            ->assertJsonPath('data.rows.0.assignmentsGraded', 1)
            ->assertJsonPath('data.rows.0.assignmentAverage', 85);
        $this->patchJson("/api/teacher/courses/{$course->id}/attempts/{$latestAttempt->id}/review", ['score' => 60, 'feedback' => 'Review the second unit.'])
            ->assertOk()
            ->assertJsonPath('data.score', 60)
            ->assertJsonPath('data.passed', false);
        $this->assertDatabaseHas('assessment_attempts', ['id' => $latestAttempt->id, 'manual_score' => 60, 'passed' => false, 'manually_reviewed_by' => $teacher->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'teacher.assessment_attempt.reviewed', 'user_id' => $teacher->id, 'auditable_id' => (string) $latestAttempt->id]);
        $export = $this->get("/api/teacher/courses/{$course->id}/gradebook/export")
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=gradebook-course-gradebook.csv');
        $this->assertStringContainsString('Gradebook Student', $export->streamedContent());
        $this->assertStringNotContainsString('Inactive Student', $export->streamedContent());
        $this->getJson("/api/teacher/courses/{$otherCourse->id}/gradebook")->assertForbidden();
        $this->patchJson("/api/teacher/courses/{$otherCourse->id}/attempts/{$latestAttempt->id}/review", ['score' => 100])->assertForbidden();
    }

    public function test_teacher_can_escalate_only_an_assigned_course_student_to_linked_guardians(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $parent = User::factory()->create(['role' => User::ROLE_PARENT]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $otherStudent = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $course = $this->course('escalation-course');
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        $parent->linkedStudents()->attach($student->id, ['status' => 'accepted', 'linked_at' => now(), 'accepted_at' => now()]);
        TeacherCourseAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $course->id]);
        Sanctum::actingAs($teacher);

        $this->postJson("/api/teacher/courses/{$course->id}/students/{$student->id}/guardian-escalation", ['reason' => 'Please review the learner assessment progress.'])
            ->assertCreated()
            ->assertJsonPath('data.notifiedGuardians', 1);
        $this->assertDatabaseHas('student_support_activities', ['type' => 'parent_escalation', 'created_by' => $teacher->id]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $parent->id, 'type' => 'student_support']);
        $this->postJson("/api/teacher/courses/{$course->id}/students/{$otherStudent->id}/guardian-escalation", ['reason' => 'This student is not enrolled here.'])->assertForbidden();
    }

    public function test_revoking_assignment_removes_workspace_access_and_teacher_cannot_use_admin_api(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $course = $this->course('revoked-course');
        $assignment = TeacherCourseAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $course->id]);
        Sanctum::actingAs($teacher);

        $this->getJson("/api/teacher/courses/{$course->id}")->assertOk();
        $assignment->update(['is_active' => false]);
        $this->getJson("/api/teacher/courses/{$course->id}")->assertForbidden();
        $this->getJson('/api/admin/courses')->assertForbidden();
        $this->getJson('/api/admin/audit-logs')->assertForbidden();
    }

    private function course(string $id): Course
    {
        return Course::create([
            'id' => $id,
            'title' => 'دورة مدرس',
            'title_en' => 'Teacher course',
            'summary' => 'ملخص',
            'summary_en' => 'Summary',
            'category' => 'Programming',
            'scope' => 'general',
            'level' => 'Beginner',
            'duration_minutes' => 60,
            'price' => 0,
            'instructor' => 'أحمد',
            'instructor_en' => 'Ahmed',
            'is_published' => true,
        ]);
    }
}
