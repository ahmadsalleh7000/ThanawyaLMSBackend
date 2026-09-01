<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseBundle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundleCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_path_catalog_exposes_ordered_roadmap_without_pricing(): void
    {
        $first = $this->course('path-one', 300, true, 'grade-1', 'semester-1');
        $second = $this->course('path-two', 400, true, 'grade-1', 'semester-2');
        $this->course('second-grade-path', 350, true, 'grade-2', 'semester-1');

        $this->getJson('/api/learning-paths')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.price')
            ->assertJsonMissingPath('data.0.standalonePrice')
            ->assertJsonPath('data.0.id', 'grade-1')
            ->assertJsonPath('data.0.type', 'learning-path')
            ->assertJsonPath('data.0.courses.1.id', $second->id)
            ->assertJsonPath('data.0.courses.1.prerequisiteCourseId', $first->id)
            ->assertJsonMissingPath('data.0.courses.0.price');
        $this->getJson('/api/learning-paths/grade-1')->assertOk()->assertJsonPath('data.courseCount', 2);
    }

    public function test_legacy_bundle_catalog_exposes_active_course_bundles_without_pricing(): void
    {
        $first = $this->course('bundle-one', 300);
        $second = $this->course('bundle-two', 400);
        $bundle = CourseBundle::create(['name' => 'مسار', 'name_en' => 'Path', 'description' => 'وصف', 'description_en' => 'Description', 'price' => 550, 'is_active' => true]);
        $bundle->courses()->attach([
            $first->id => ['position' => 1, 'prerequisite_course_id' => null],
            $second->id => ['position' => 2, 'prerequisite_course_id' => $first->id],
        ]);

        $this->getJson('/api/bundles')->assertOk()->assertJsonPath('data.0.type', 'learning-path');
        $this->getJson('/api/bundles/'.$bundle->id)->assertOk()->assertJsonPath('data.courseCount', 2);
    }

    public function test_path_is_unavailable_if_any_course_is_unpublished(): void
    {
        $available = $this->course('available-course', 200);
        $hidden = $this->course('hidden-course', 200, false);
        $bundle = CourseBundle::create(['name' => 'مسار', 'name_en' => 'Path', 'price' => 300, 'is_active' => true]);
        $bundle->courses()->attach([$available->id => ['position' => 1], $hidden->id => ['position' => 2]]);

        $this->getJson('/api/bundles')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/bundles/'.$bundle->id)->assertNotFound();
    }

    private function course(string $id, float $price, bool $published = true, string $grade = 'grade-1', string $semester = 'semester-1'): Course
    {
        return Course::create(['id' => $id, 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => $grade, 'semester' => $semester, 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => $price, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => $published]);
    }
}
