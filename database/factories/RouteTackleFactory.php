<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RouteTackle>
 */
class RouteTackleFactory extends Factory
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
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['rope', 'ladder', 'sling', 'karabiner']),
            'length' => $this->faker->numberBetween(5, 100),
            'optional' => $this->faker->boolean(20),
            'quantity' => $this->faker->numberBetween(1, 3),
        ];
    }
}
