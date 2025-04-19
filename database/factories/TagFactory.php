<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tag' => fake()->word(),
            'type' => Str::random(2),
            'category' => Str::random(2),
            'image' => fake()->imageUrl(),
            'description' => fake()->sentence(),
            'assignable' => true,
        ];
    }
}
