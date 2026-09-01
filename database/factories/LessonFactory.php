<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'title_en' => fake()->sentence(3),
            'duration_minutes' => fake()->numberBetween(10, 60),
            'position' => 1,
            'is_preview' => false,
        ];
    }
}
