<?php

namespace Database\Factories;

use App\Models\Cave;
use App\Models\CaveSystem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Route>
 */
class RouteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(3, true);
        return [
            'cave_system_id' => CaveSystem::factory(),
            'entrance_id' => Cave::factory(),
            'exit_id' => Cave::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'description' => $this->faker->paragraphs(3, true),
            'duration' => $this->faker->randomElement(['2 hours', '4-5 hours', 'Full day']),
            'grade' => $this->faker->numberBetween(1, 5),
            'hero_image' => 'https://images.unsplash.com/photo-1504386106331-3e4e71712b38?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60', // Placeholder cave image
        ];
    }
}
