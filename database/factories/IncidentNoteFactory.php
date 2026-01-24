<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncidentNoteFactory extends Factory
{
    protected $model = IncidentNote::class;

    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'user_id' => User::factory(),
            'content' => $this->faker->paragraph,
        ];
    }
}
