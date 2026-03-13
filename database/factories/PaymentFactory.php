<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'transaction_ref' => 'TXN_' . fake()->unique()->numerify('##########'),
            'payment_method' => 'telebirr',
            'amount' => fake()->randomFloat(2, 10, 500),
            'currency' => 'ETB',
            'status' => PaymentStatus::PENDING,
            'telebirr_transaction_id' => null,
            'payment_data' => null,
            'paid_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::COMPLETED,
            'telebirr_transaction_id' => 'TELEBIRR_' . fake()->numerify('##########'),
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::FAILED,
        ]);
    }
}
