<?php

namespace Database\Factories;

use App\Models\CaveSystem;
use App\Models\Cave;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class TripFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => fake()->sentence(),
            'cave_system_id' => CaveSystem::factory(),
            'entrance_cave_id' => Cave::factory(),
            'exit_cave_id' => Cave::factory(),
            'name' => fake()->word(),
            'start_time' => now()->subMinutes(fake()->numberBetween(1, 60))->toDateTimeString(),
            'end_time' => now()->addMinutes(fake()->numberBetween(1, 60))->toDateTimeString(),
        ];
    }
}
