<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hut>
 */
class HutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => \App\Models\Club::factory(),
            'name' => $this->faker->words(3, true) . ' Hut',
            'description' => $this->faker->paragraph(),
            'location_lat' => $this->faker->latitude(),
            'location_lng' => $this->faker->longitude(),
            'amenities' => ['Electricity', 'Water'],
            'booking_info' => 'Contact club',
        ];
    }
}
