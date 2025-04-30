<?php

namespace Database\Factories;

use App\Models\TripUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripUserFactory extends Factory
{
    protected $model = TripUser::class;

    public function definition(): array
    {
        return [
            'trip_id' => 1, // Replace with a valid trip ID in your tests
            'user_id' => 1, // Replace with a valid user ID in your tests
        ];
    }
}
