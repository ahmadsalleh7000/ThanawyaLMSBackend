<?php

namespace Tests\Feature\Api;

use App\Models\AcademicSubject;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\ParentStudentLink;
use App\Models\StudentSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParentReportingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_sees_only_linked_student_progress(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $parent->linkedStudents()->attach($student->id, ['grade' => 'grade-1', 'linked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($parent);

        $this->getJson('/api/parent/students')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $student->id);
        $this->getJson('/api/parent/students/'.$student->id)->assertOk();
        $this->getJson('/api/parent/students/'.$otherStudent->id)->assertNotFound();
    }

    public function test_student_cannot_access_parent_reports(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->getJson('/api/parent/students')->assertForbidden();
    }

    public function test_pending_rejected_revoked_and_inactive_relationships_never_expose_reports(): void
    {
        $parent = User::factory()->create(['role' => User::ROLE_PARENT]);
        $students = collect(['pending', 'rejected', 'revoked'])->mapWithKeys(function (string $status) use ($parent) {
            $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
            ParentStudentLink::create([
                'parent_id' => $parent->id,
                'student_id' => $student->id,
                'status' => $status,
                'linked_at' => now(),
                'accepted_at' => $status === 'revoked' ? now()->subDay() : null,
                'revoked_at' => $status === 'revoked' ? now() : null,
                'rejected_at' => $status === 'rejected' ? now() : null,
            ]);

            return [$status => $student];
        });
        $inactive = User::factory()->create(['role' => User::ROLE_STUDENT, 'is_active' => false]);
        ParentStudentLink::create([
            'parent_id' => $parent->id,
            'student_id' => $inactive->id,
            'status' => 'accepted',
            'linked_at' => now(),
            'accepted_at' => now(),
        ]);
        Sanctum::actingAs($parent);

        $this->getJson('/api/parent/students')->assertOk()->assertJsonCount(0, 'data');
        $students->push($inactive)->each(fn (User $student) => $this
            ->getJson('/api/parent/students/'.$student->id)
            ->assertNotFound());
    }

    public function test_deactivated_parent_cannot_access_an_existing_report_relationship(): void
    {
        $parent = User::factory()->create(['role' => User::ROLE_PARENT, 'is_active' => false]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        ParentStudentLink::create([
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'status' => 'accepted',
            'linked_at' => now(),
            'accepted_at' => now(),
        ]);
        Sanctum::actingAs($parent);

        $this->getJson('/api/parent/students/'.$student->id)->assertForbidden();
    }

    public function test_report_calculates_each_course_progress_independently(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $student = User::factory()->create(['role' => 'student']);
        $parent->linkedStudents()->attach($student->id, ['grade' => 'grade-1', 'linked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $course = Course::create(['id' => 'parent-course', 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
        $lessonOne = $course->lessons()->create(['slug' => 'lesson-1', 'title' => 'درس 1', 'title_en' => 'Lesson 1', 'duration_minutes' => 10, 'position' => 1]);
        $course->lessons()->create(['slug' => 'lesson-2', 'title' => 'درس 2', 'title_en' => 'Lesson 2', 'duration_minutes' => 10, 'position' => 2]);
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);
        $student->lessonCompletions()->create(['lesson_id' => $lessonOne->id, 'completed_at' => now()]);
        $student->lessonWorkspaceStates()->create(['lesson_id' => $lessonOne->id, 'note' => 'Private student note', 'is_bookmarked' => true]);
        Sanctum::actingAs($parent);

        $response = $this->getJson('/api/parent/students/'.$student->id)->assertOk()
            ->assertJsonPath('data.progress', 50)
            ->assertJsonPath('data.courseProgress.0.progress', 50)
            ->assertJsonPath('data.courseProgress.0.completedLessons', 1)
            ->assertJsonPath('data.learningMinutes', 10)
            ->assertJsonPath('data.completedLessons', 1)
            ->assertJsonPath('data.recentActivity.0.lessonId', 'lesson-1')
            ->assertJsonPath('data.weeklyCompletedLessons', 1)
            ->assertJsonPath('data.currentStreak', 1)
            ->assertJsonPath('data.attentionStatus', 'on_track')
            ->assertJsonPath('data.schedule.todayTasks.0.id', 'lesson-2')
            ->assertJsonPath('data.schedule.weeklyGoal.completedMinutes', 10)
            ->assertJsonCount(7, 'data.weeklyActivity');
        $response->assertJsonMissing(['note' => 'Private student note'])->assertJsonMissing(['isBookmarked' => true]);
    }

    public function test_parent_report_uses_subscription_scoped_courses_without_manual_enrollment(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'registration_status' => 'approved']);
        $parent->linkedStudents()->attach($student->id, ['grade' => 'grade-2', 'linked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $selectedTerm = $this->term('grade-2', 'semester-2');
        $otherTerm = $this->term('grade-2', 'semester-1');
        StudentSubscription::create(['student_id' => $student->id, 'subscription_plan_id' => SubscriptionPlan::firstOrFail()->id, 'academic_term_id' => $selectedTerm->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $allowed = $this->academicCourse($selectedTerm, 'parent-visible-semester-two');
        $denied = $this->academicCourse($otherTerm, 'parent-hidden-semester-one');
        $lesson = $allowed->lessons()->create(['slug' => 'allowed-lesson', 'title' => 'درس متاح', 'title_en' => 'Allowed lesson', 'duration_minutes' => 20, 'position' => 1, 'status' => 'published']);
        $student->lessonCompletions()->create(['lesson_id' => $lesson->id, 'completed_at' => now()]);
        Sanctum::actingAs($parent);

        $response = $this->getJson('/api/parent/students/'.$student->id)->assertOk();

        $courseIds = collect($response->json('data.courseProgress'))->pluck('courseId');
        $this->assertTrue($courseIds->contains($allowed->id));
        $this->assertFalse($courseIds->contains($denied->id));
        $response->assertJsonPath('data.courseProgress.0.progress', 100)
            ->assertJsonPath('data.courses.0', $allowed->id)
            ->assertJsonPath('data.schedule.weeklyGoal.completedMinutes', 20);
    }

    private function term(string $grade, string $semester): AcademicTerm
    {
        return AcademicTerm::active()->where('code', $semester)->whereHas('grade', fn ($query) => $query->where('code', $grade))->firstOrFail();
    }

    private function academicCourse(AcademicTerm $term, string $id): Course
    {
        $offering = AcademicSubject::where('academic_term_id', $term->id)->firstOrFail();

        return Course::create(['id' => $id, 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => $offering->subject->name_en, 'scope' => 'academic', 'academic_subject_id' => $offering->id, 'grade' => $term->grade->code, 'semester' => $term->code, 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
    }
}
