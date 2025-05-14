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
            'trip_id' => \App\Models\Trip::factory(), // Dynamically create a Trip and use its ID
            'user_id' => \App\Models\User::factory(), // Dynamically create a User and use its ID
        ];
    }
}
