<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseReviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_can_submit_review_for_moderation(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'enrolled_at' => now()]);

        $this->actingAs($student)->postJson('/api/courses/'.$course->id.'/reviews', [
            'rating' => 4, 'comment' => 'The practical lessons made the concepts much clearer.',
        ])->assertCreated()->assertJsonPath('review.status', 'pending');

        $this->assertDatabaseHas('course_reviews', ['user_id' => $student->id, 'course_id' => $course->id, 'rating' => 4]);
    }

    public function test_unenrolled_student_cannot_review_and_only_published_reviews_are_public(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true]);
        $published = CourseReview::create(['user_id' => $student->id, 'course_id' => $course->id, 'rating' => 5, 'comment' => 'Published review content.', 'status' => 'published']);
        CourseReview::create(['user_id' => User::factory()->create(['role' => 'student'])->id, 'course_id' => $course->id, 'rating' => 2, 'comment' => 'Pending review content.', 'status' => 'pending']);

        $this->actingAs(User::factory()->create(['role' => 'student']))->postJson('/api/courses/'.$course->id.'/reviews', [
            'rating' => 4, 'comment' => 'I should not be allowed to submit this review.',
        ])->assertForbidden();
        $this->getJson('/api/courses/'.$course->id.'/reviews')->assertOk()
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.verifiedEnrollment', true)
            ->assertJsonPath('meta.reviewCount', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_reviews_can_be_sorted_and_student_receives_submission_state(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
        CourseReview::create(['user_id' => $student->id, 'course_id' => $course->id, 'rating' => 2, 'comment' => 'A pending student review.', 'status' => 'pending']);
        CourseReview::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id, 'rating' => 3, 'comment' => 'A published lower review.', 'status' => 'published']);
        CourseReview::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id, 'rating' => 5, 'comment' => 'A published higher review.', 'status' => 'published']);

        $this->actingAs($student)->getJson('/api/courses/'.$course->id.'/reviews?sort=highest')
            ->assertOk()
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('meta.canReview', true)
            ->assertJsonPath('meta.ownReview.status', 'pending');
    }

    public function test_student_can_edit_and_delete_their_review_with_moderation_reset(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
        CourseReview::create(['user_id' => $student->id, 'course_id' => $course->id, 'rating' => 5, 'comment' => 'Previously published review.', 'status' => 'published', 'moderated_at' => now()]);

        $this->actingAs($student)->postJson('/api/courses/'.$course->id.'/reviews', [
            'rating' => 4, 'comment' => 'Updated feedback that should be moderated again.',
        ])->assertOk()->assertJsonPath('review.status', 'pending');
        $this->assertDatabaseHas('course_reviews', ['user_id' => $student->id, 'rating' => 4, 'status' => 'pending']);

        $this->deleteJson('/api/courses/'.$course->id.'/reviews')->assertNoContent();
        $this->assertDatabaseMissing('course_reviews', ['user_id' => $student->id, 'course_id' => $course->id]);
    }
}
