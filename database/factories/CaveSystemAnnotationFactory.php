<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CaveSystem;
use App\Models\CaveSystemAnnotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaveSystemAnnotation>
 */
class CaveSystemAnnotationFactory extends Factory
{
    protected $model = CaveSystemAnnotation::class;

    public function definition(): array
    {
        return [
            'cave_system_id' => CaveSystem::factory(),
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => [
                    [
                        'type' => 'Feature',
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [fake()->longitude(-5, 2), fake()->latitude(50, 56)],
                        ],
                        'properties' => [
                            'annotation_type' => 'parking',
                            'description' => 'Main car park',
                        ],
                    ],
                ],
            ],
        ];
    }
}
