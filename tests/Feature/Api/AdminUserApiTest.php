<?php

namespace Tests\Feature\Api;

use App\Models\AcademicSubject;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\StudentSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_accounts_and_view_student_learning_and_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Mona Student', 'email' => 'mona@example.test']);
        $course = $this->course();
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users?search=Mona&role=student')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'mona@example.test');
        $this->getJson('/api/admin/users/'.$student->id)->assertOk()->assertJsonPath('data.courses.0.id', $course->id)->assertJsonPath('data.courses.0.progress', 0);
    }

    public function test_admin_student_profile_lists_subscription_scoped_courses_without_enrollment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'registration_status' => 'approved']);
        $selectedTerm = $this->term('grade-1', 'semester-2');
        $otherTerm = $this->term('grade-1', 'semester-1');
        StudentSubscription::create(['student_id' => $student->id, 'subscription_plan_id' => SubscriptionPlan::firstOrFail()->id, 'academic_term_id' => $selectedTerm->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $allowed = $this->academicCourse($selectedTerm, 'admin-visible-semester-two');
        $denied = $this->academicCourse($otherTerm, 'admin-hidden-semester-one');
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users/'.$student->id)->assertOk();
        $courseIds = collect($response->json('data.courses'))->pluck('id');

        $this->assertTrue($courseIds->contains($allowed->id));
        $this->assertFalse($courseIds->contains($denied->id));
        $this->assertCount(0, $student->enrollments);
    }

    public function test_admin_can_link_and_unlink_a_parent_and_student_with_valid_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $parent = User::factory()->create(['role' => 'parent']);
        $student = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/parent-student-links', ['parent_id' => $parent->id, 'student_id' => $student->id, 'grade' => 'grade-1'])
            ->assertCreated()->assertJsonPath('data.grade', 'grade-1');
        $this->postJson('/api/admin/parent-student-links', ['parent_id' => $student->id, 'student_id' => $parent->id])->assertNotFound();
        $this->deleteJson('/api/admin/parent-student-links/'.$parent->id.'/'.$student->id)->assertNoContent();
        $this->assertDatabaseCount('parent_student_links', 0);
    }

    public function test_deactivation_revokes_tokens_and_blocks_login_until_reactivated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'password' => 'password']);
        $student->createToken('phone');
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users/'.$student->id.'/deactivate')->assertOk()->assertJsonPath('data.isActive', false);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/auth/login', ['email' => $student->email, 'password' => 'password'])->assertUnprocessable();
        $this->postJson('/api/admin/users/'.$admin->id.'/deactivate')->assertConflict();
        $this->postJson('/api/admin/users/'.$student->id.'/activate')->assertOk()->assertJsonPath('data.isActive', true);
        $this->postJson('/api/auth/login', ['email' => $student->email, 'password' => 'password'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deactivated', 'auditable_id' => (string) $student->id]);
    }

    public function test_non_admin_cannot_browse_users(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'parent']));
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    private function course(): Course
    {
        return Course::create(['id' => 'managed-course', 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 0, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
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
