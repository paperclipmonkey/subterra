<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Medal;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedalFactory extends Factory
{
    protected $model = Medal::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
        ];
    }
}
