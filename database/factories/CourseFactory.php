<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->slug(3),
            'title' => fake()->sentence(3),
            'title_en' => fake()->sentence(3),
            'summary' => fake()->sentence(10),
            'summary_en' => fake()->sentence(10),
            'description' => fake()->paragraphs(2, true),
            'description_en' => fake()->paragraphs(2, true),
            'image_url' => null,
            'category' => 'Programming',
            'scope' => 'academic',
            'grade' => fake()->randomElement(['grade-1', 'grade-2', 'grade-3']),
            'semester' => fake()->randomElement(['semester-1', 'semester-2']),
            'level' => fake()->randomElement(['Beginner', 'Intermediate', 'Advanced']),
            'duration_minutes' => fake()->numberBetween(60, 600),
            'price' => fake()->randomElement([0, 250, 500]),
            'rating' => 0,
            'enrolled_count' => 0,
            'instructor' => fake()->name(),
            'instructor_en' => fake()->name(),
            'is_published' => true,
        ];
    }
}
