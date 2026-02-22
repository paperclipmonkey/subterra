<?php

namespace Database\Factories;

use App\Models\Callout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalloutFactory extends Factory
{
    protected $model = Callout::class;

    public function definition(): array
    {
        return [
            'id' => str()->random(16),
            'user_id' => User::factory(),
            'cave_id' => \App\Models\Cave::factory(),
            'description' => $this->faker->sentence,
            'callout_time' => $this->faker->dateTimeBetween('now', '+1 week'),
            'status' => 'active',
        ];
    }
}
