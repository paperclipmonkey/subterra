<?php

namespace Database\Factories;

use App\Models\CaveSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class CaveFactory extends Factory
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
            'description' => fake()->sentence(30),
            'location_name' => fake()->city(),
            'location_country' => fake()->country(),
            'location_lat' => fake()->randomFloat(3, 50,58),
            'location_lng' => fake()->randomFloat(3,-6,1),
            'location_alt' => fake()->randomDigitNotNull(),
            'slug' => fake()->slug(),
            'cave_system_id' => CaveSystem::factory()->create()->id,
        ];
    }
}
