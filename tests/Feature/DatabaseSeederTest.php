<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seed_keeps_local_learning_data_without_admin_qa_noise(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 3);
        foreach ([User::ROLE_STUDENT, User::ROLE_PARENT, User::ROLE_ADMIN] as $role) {
            $user = User::where('role', $role)->sole();
            $this->assertTrue($user->hasVerifiedEmail());
            $this->assertTrue($user->isEligibleForAccess());
            $this->assertTrue(Hash::check('password123', $user->password));
        }

        $this->assertDatabaseCount('courses', 3);
        $this->assertDatabaseCount('lessons', 9);
        $this->assertDatabaseCount('assessments', 1);
        $this->assertDatabaseCount('assessment_questions', 2);
        $student = User::where('role', User::ROLE_STUDENT)->sole();
        $parent = User::where('role', User::ROLE_PARENT)->sole();
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => 'python-1']);
        $this->assertTrue($parent->linkedStudents()->whereKey($student->id)->exists());

        foreach (Course::with('lessons')->get() as $course) {
            $this->assertSame($course->lessons->sum('duration_minutes'), $course->duration_minutes);
            $this->assertSame('0.0', $course->rating);
            $this->assertSame(0, $course->enrolled_count);
        }

        foreach ([
            'orders', 'manual_payment_submissions', 'student_support_cases',
            'application_incidents', 'reconciliation_alerts', 'announcements',
            'user_notifications', 'wishlist_items', 'question_bank_questions',
        ] as $table) {
            $this->assertDatabaseEmpty($table);
        }
    }

    public function test_reseeding_preserves_local_edits_and_credentials_without_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);

        $passwords = [];
        foreach (User::all() as $user) {
            $user->update(['name' => "Edited {$user->role}", 'password' => 'ChangedLocalPassword123!']);
            $passwords[$user->id] = $user->password;
        }
        Course::findOrFail('python-1')->update(['title_en' => 'Edited Python course', 'is_published' => false]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('courses', 3);
        $this->assertDatabaseCount('lessons', 9);
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('parent_student_links', 1);
        $this->assertDatabaseCount('assessment_questions', 2);
        foreach (User::all() as $user) {
            $this->assertSame("Edited {$user->role}", $user->name);
            $this->assertSame($passwords[$user->id], $user->password);
        }
        $this->assertDatabaseHas('courses', ['id' => 'python-1', 'title_en' => 'Edited Python course', 'is_published' => false]);
    }

    public function test_initial_seed_leaves_unrelated_existing_courses_available(): void
    {
        $course = Course::factory()->create(['id' => 'react-1', 'is_published' => true]);

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue($course->fresh()->is_published);
        $this->assertNull($course->fresh()->archived_at);
    }
}
