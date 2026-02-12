<?php

namespace Database\Factories;

use App\Models\TripMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripMediaFactory extends Factory
{
    protected $model = TripMedia::class;

    public function definition(): array
    {
        return [
            'trip_id' => \App\Models\Trip::factory(), // Dynamically associate with a Trip instance
            'filename' => $this->faker->word.'.jpg',
            'title' => $this->faker->optional()->sentence(3),
            'copyright' => $this->faker->optional()->name,
            'photographer' => $this->faker->optional()->name,
            'taken_at' => $this->faker->optional()->dateTimeThisYear(),
        ];
    }
}
