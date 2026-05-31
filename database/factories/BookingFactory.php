<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Permit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'permit_id' => Permit::factory(),
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'participants' => fake()->numberBetween(1, 8),
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
            'conditions_accepted_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
        ]);
    }
}
