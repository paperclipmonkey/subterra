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
            'filename' => $this->faker->word . '.jpg',
        ];
    }
}
