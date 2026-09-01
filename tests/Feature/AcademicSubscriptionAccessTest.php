<?php

namespace Tests\Feature;

use App\Models\AcademicSubject;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\StudentSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Services\StudentContentAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicSubscriptionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_admin_can_manage_structure_and_duplicate_subject_context_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor', 'is_active' => true]);
        $term = AcademicTerm::firstOrFail();
        $subject = $term->academicSubjects()->firstOrFail()->subject;

        $this->actingAs($admin)->get(route('admin.academics.index'))->assertOk()->assertSee('الهيكل الأكاديمي');
        $this->actingAs($admin)->post(route('admin.academics.subjects.store'), ['code' => 'physics', 'name_ar' => 'الفيزياء', 'name_en' => 'Physics'])->assertRedirect();
        $this->assertDatabaseHas('subjects', ['code' => 'physics']);
        $this->actingAs($admin)->post(route('admin.academics.offerings.store'), ['academic_term_id' => $term->id, 'subject_id' => $subject->id])->assertSessionHasErrors('subject_id');
    }

    public function test_active_term_subscription_grants_current_and_future_courses_only_in_its_scope(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'registration_status' => 'approved']);
        $term = AcademicTerm::active()->whereHas('grade', fn ($query) => $query->where('code', 'grade-1'))->where('code', 'semester-1')->firstOrFail();
        $otherTerm = AcademicTerm::active()->whereHas('grade', fn ($query) => $query->where('code', 'grade-2'))->where('code', 'semester-1')->firstOrFail();
        $plan = SubscriptionPlan::firstOrFail();
        StudentSubscription::create(['student_id' => $student->id, 'subscription_plan_id' => $plan->id, 'academic_term_id' => $term->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);

        $included = $this->courseFor($term, 'included-course');
        $excluded = $this->courseFor($otherTerm, 'other-grade-course');
        $access = app(StudentContentAccessService::class);

        $this->assertTrue($access->canAccessCourse($student, $included));
        $this->assertFalse($access->canAccessCourse($student, $excluded));
        $future = $this->courseFor($term, 'published-later');
        $this->assertTrue($access->canAccessCourse($student, $future));
        $this->assertCount(0, $student->enrollments);
    }

    public function test_expired_subscription_and_unpublished_content_are_denied(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'registration_status' => 'approved']);
        $term = AcademicTerm::active()->firstOrFail();
        $plan = SubscriptionPlan::firstOrFail();
        StudentSubscription::create(['student_id' => $student->id, 'subscription_plan_id' => $plan->id, 'academic_term_id' => $term->id, 'status' => 'active', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subMinute()]);
        $course = $this->courseFor($term, 'expired-course');
        $this->assertFalse(app(StudentContentAccessService::class)->canAccessCourse($student, $course));

        StudentSubscription::where('student_id', $student->id)->update(['ends_at' => now()->addMonth()]);
        $lesson = $course->lessons()->create(['slug' => 'draft-lesson', 'title' => 'مسودة', 'title_en' => 'Draft', 'duration_minutes' => 10, 'position' => 1, 'status' => 'draft']);
        $this->assertFalse(app(StudentContentAccessService::class)->canAccessLesson($student, $lesson));
    }

    public function test_published_lesson_inside_draft_unit_is_denied(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'registration_status' => 'approved']);
        $term = AcademicTerm::active()->firstOrFail();
        StudentSubscription::create(['student_id' => $student->id, 'subscription_plan_id' => SubscriptionPlan::firstOrFail()->id, 'academic_term_id' => $term->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);
        $course = $this->courseFor($term, 'draft-parent-course');
        $unit = Unit::create(['academic_subject_id' => $course->academic_subject_id, 'course_id' => $course->id, 'slug' => 'draft-parent', 'title_ar' => 'وحدة', 'title_en' => 'Unit', 'position' => 1, 'status' => 'draft']);
        $lesson = $course->lessons()->create(['unit_id' => $unit->id, 'slug' => 'published-child', 'title' => 'درس', 'title_en' => 'Lesson', 'duration_minutes' => 10, 'position' => 1, 'status' => 'published']);

        $this->assertFalse(app(StudentContentAccessService::class)->canAccessLesson($student, $lesson));
    }

    public function test_direct_url_cannot_open_assessment_from_another_scope(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'registration_status' => 'approved']);
        $term = AcademicTerm::active()->whereHas('grade', fn ($query) => $query->where('code', 'grade-1'))->firstOrFail();
        $otherTerm = AcademicTerm::active()->whereHas('grade', fn ($query) => $query->where('code', 'grade-2'))->firstOrFail();
        StudentSubscription::create(['student_id' => $student->id, 'subscription_plan_id' => SubscriptionPlan::firstOrFail()->id, 'academic_term_id' => $term->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);
        $course = $this->courseFor($otherTerm, 'forbidden-assessment');

        $this->actingAs($student)->getJson('/api/courses/'.$course->id.'/assessment')->assertForbidden();
    }

    private function courseFor(AcademicTerm $term, string $id): Course
    {
        $offering = AcademicSubject::where('academic_term_id', $term->id)->firstOrFail();

        return Course::create(['id' => $id, 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => $offering->subject->name_en, 'academic_subject_id' => $offering->id, 'grade' => $term->grade->code, 'semester' => $term->code, 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
    }
}
