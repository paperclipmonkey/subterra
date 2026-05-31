<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RouteMedia>
 */
class RouteMediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'route_id' => Route::factory(),
            'path' => $this->faker->imageUrl(),
            'type' => $this->faker->randomElement(['photo', 'survey']),
            'caption' => $this->faker->sentence(),
        ];
    }
}
