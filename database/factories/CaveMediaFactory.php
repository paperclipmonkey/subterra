<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CaveMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

class CaveMediaFactory extends Factory
{
    protected $model = CaveMedia::class;

    public function definition(): array
    {
        return [
            'cave_id' => \App\Models\Cave::factory(),
            'type' => $this->faker->randomElement(['hero', 'hero_video', 'entrance', 'gallery']),
            'filename' => $this->faker->word.'.'.$this->faker->randomElement(['jpg', 'webp', 'mp4']),
            'title' => $this->faker->optional()->sentence(3),
            'photographer' => $this->faker->optional()->name(),
            'copyright' => $this->faker->optional()->name(),
        ];
    }
}
