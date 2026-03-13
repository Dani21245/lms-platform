<?php

namespace Database\Factories;

use App\Enums\CourseStatus;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instructor_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'requirements' => fake()->sentence(),
            'what_you_will_learn' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 10, 500),
            'currency' => 'ETB',
            'level' => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
            'language' => 'en',
            'status' => CourseStatus::PUBLISHED,
            'is_featured' => false,
            'duration_minutes' => fake()->numberBetween(60, 600),
        ];
    }
}
