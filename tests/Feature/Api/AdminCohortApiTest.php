<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCohortApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_cohort_and_add_only_active_course_students(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $course = Course::factory()->create(['id' => 'cohort-course']);
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);
        Sanctum::actingAs($admin);

        $cohort = $this->postJson('/api/admin/cohorts', ['course_id' => $course->id, 'teacher_id' => $teacher->id, 'name' => 'Morning class', 'code' => 'morning'])->assertCreated()->assertJsonPath('data.status', 'active')->json('data');
        $this->postJson('/api/admin/cohorts/'.$cohort['id'].'/students', ['student_id' => $student->id])->assertOk();
        $this->postJson('/api/admin/cohorts/'.$cohort['id'].'/students', ['student_id' => $other->id])->assertUnprocessable();
        $this->assertDatabaseHas('cohort_members', ['cohort_id' => $cohort['id'], 'student_id' => $student->id]);
        $this->assertDatabaseCount('cohort_members', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cohort.student_added']);
    }

    public function test_students_cannot_use_admin_cohort_api(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student);
        $this->getJson('/api/admin/cohorts')->assertForbidden();
    }
}
