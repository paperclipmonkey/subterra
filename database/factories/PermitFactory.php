<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PermitFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->paragraph(),
            'conditions' => fake()->paragraph(),
            'has_max_groups_per_day' => false,
            'max_groups_per_day' => null,
            'auto_approve' => false,
            'booking_info' => fake()->paragraph(),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function withMaxGroups(int $max = 2): static
    {
        return $this->state(fn () => [
            'has_max_groups_per_day' => true,
            'max_groups_per_day' => $max,
        ]);
    }

    public function autoApprove(): static
    {
        return $this->state(fn () => [
            'auto_approve' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
