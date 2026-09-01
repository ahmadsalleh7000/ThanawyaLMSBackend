<?php

namespace Tests\Feature;

use App\Models\AcademicSubject;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\StudentSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseScopeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_catalog_contains_general_and_only_subscribed_academic_scope(): void
    {
        $first = $this->term('grade-1', 'semester-1');
        $second = $this->term('grade-2', 'semester-1');
        $student = $this->subscribedStudent($first);
        $allowed = $this->academicCourse($first, 'math-grade-one');
        $denied = $this->academicCourse($second, 'physics-grade-two');
        $general = $this->generalCourse('study-skills');

        Sanctum::actingAs($student);
        $ids = collect($this->getJson('/api/courses')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($allowed->id));
        $this->assertTrue($ids->contains($general->id));
        $this->assertFalse($ids->contains($denied->id));
        $this->getJson('/api/courses/'.$denied->id)->assertForbidden();
        $this->getJson('/api/courses/'.$general->id)->assertOk();
    }

    public function test_expired_subscription_and_draft_publication_do_not_grant_visibility(): void
    {
        $term = $this->term('grade-1', 'semester-1');
        $student = $this->subscribedStudent($term, now()->subMinute());
        $academic = $this->academicCourse($term, 'expired-academic');
        $draftGeneral = $this->generalCourse('draft-general', false);

        Sanctum::actingAs($student);
        $this->getJson('/api/courses')->assertOk()->assertJsonMissing(['id' => $academic->id])->assertJsonMissing(['id' => $draftGeneral->id]);
        $this->getJson('/api/courses/'.$academic->id)->assertForbidden();
        $this->getJson('/api/courses/'.$draftGeneral->id)->assertNotFound();
    }

    public function test_multiple_active_subscriptions_combine_academic_scopes(): void
    {
        $first = $this->term('grade-1', 'semester-1');
        $second = $this->term('grade-2', 'semester-2');
        $student = $this->subscribedStudent($first);
        $this->subscribe($student, $second);
        $one = $this->academicCourse($first, 'scope-one');
        $two = $this->academicCourse($second, 'scope-two');

        Sanctum::actingAs($student);
        $ids = collect($this->getJson('/api/courses')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($one->id));
        $this->assertTrue($ids->contains($two->id));
    }

    public function test_student_course_payload_contains_only_available_lessons(): void
    {
        $term = $this->term('grade-1', 'semester-1');
        $student = $this->subscribedStudent($term);
        $course = $this->academicCourse($term, 'filtered-lessons');
        $course->lessons()->create(['slug' => 'published', 'title' => 'منشور', 'title_en' => 'Published', 'duration_minutes' => 10, 'position' => 1, 'status' => 'published']);
        $course->lessons()->create(['slug' => 'draft', 'title' => 'مسودة', 'title_en' => 'Draft', 'duration_minutes' => 10, 'position' => 2, 'status' => 'draft']);
        $course->lessons()->create(['slug' => 'future', 'title' => 'لاحق', 'title_en' => 'Future', 'duration_minutes' => 10, 'position' => 3, 'status' => 'published', 'available_from' => now()->addDay()]);

        Sanctum::actingAs($student);
        $lessons = collect($this->getJson('/api/courses/'.$course->id)->assertOk()->json('data.lessons'));

        $this->assertSame(['published'], $lessons->pluck('id')->all());
    }

    public function test_admin_can_create_general_course_and_switching_scope_clears_academic_context(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);
        $payload = $this->payload('general-new') + ['scope' => 'general'];
        $this->postJson('/api/admin/courses', $payload)->assertCreated()->assertJsonPath('data.scope', 'general');
        $this->assertDatabaseHas('courses', ['id' => 'general-new', 'scope' => 'general', 'academic_subject_id' => null, 'grade' => null, 'semester' => null]);

        $term = $this->term('grade-1', 'semester-1');
        $academic = $this->academicCourse($term, 'scope-change');
        $academic->update(['is_published' => false]);
        $this->patchJson('/api/admin/courses/'.$academic->id, ['scope' => 'general'])->assertOk();
        $this->assertDatabaseHas('courses', ['id' => $academic->id, 'scope' => 'general', 'academic_subject_id' => null, 'grade' => null, 'semester' => null]);
    }

    public function test_academic_course_requires_a_valid_academic_context(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->postJson('/api/admin/courses', $this->payload('invalid-academic') + ['scope' => 'academic', 'grade' => null, 'semester' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('academic_subject_id');
    }

    private function term(string $grade, string $semester): AcademicTerm
    {
        return AcademicTerm::active()->where('code', $semester)->whereHas('grade', fn ($query) => $query->where('code', $grade))->firstOrFail();
    }

    private function subscribedStudent(AcademicTerm $term, $endsAt = null): User
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'registration_status' => 'approved']);
        $this->subscribe($student, $term, $endsAt);

        return $student;
    }

    private function subscribe(User $student, AcademicTerm $term, $endsAt = null): void
    {
        StudentSubscription::create(['student_id' => $student->id, 'subscription_plan_id' => SubscriptionPlan::firstOrFail()->id, 'academic_term_id' => $term->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => $endsAt ?? now()->addMonth()]);
    }

    private function academicCourse(AcademicTerm $term, string $id): Course
    {
        $offering = AcademicSubject::where('academic_term_id', $term->id)->firstOrFail();

        return Course::create($this->payload($id) + ['scope' => 'academic', 'academic_subject_id' => $offering->id, 'grade' => $term->grade->code, 'semester' => $term->code, 'is_published' => true]);
    }

    private function generalCourse(string $id, bool $published = true): Course
    {
        return Course::create($this->payload($id) + ['scope' => 'general', 'grade' => null, 'semester' => null, 'academic_subject_id' => null, 'is_published' => $published]);
    }

    private function payload(string $id): array
    {
        return ['id' => $id, 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed'];
    }
}
