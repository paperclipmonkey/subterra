<?php

namespace Database\Factories;

use App\Models\CaveSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class CaveSystemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'slug' => fake()->slug(),
            'description' => fake()->sentence(30),
            'length' => fake()->randomDigitNotNull() * 101,
            'vertical_range' => fake()->randomDigitNotNull() * 11,
        ];
    }
}
