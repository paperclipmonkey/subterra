<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Callout;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        return [
            'callout_id' => Callout::factory(),
            'status' => 'open',
        ];
    }
}
