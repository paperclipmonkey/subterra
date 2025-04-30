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
            'trip_id' => 1, // Replace with a valid trip ID in your tests
            'filename' => $this->faker->word . '.jpg',
        ];
    }
}
