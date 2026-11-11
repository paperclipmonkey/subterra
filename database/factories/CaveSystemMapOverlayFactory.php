<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CaveSystem;
use App\Models\CaveSystemMapOverlay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaveSystemMapOverlay>
 */
class CaveSystemMapOverlayFactory extends Factory
{
    protected $model = CaveSystemMapOverlay::class;

    public function definition(): array
    {
        $original = $this->faker->word().'.tif';

        return [
            'cave_system_id' => CaveSystem::factory(),
            'name' => $this->faker->words(2, true),
            'filename' => hash('sha256', $original.$this->faker->unique()->numerify('######')).'.tif',
            'original_filename' => $original,
            'mime_type' => 'image/tiff',
            'size' => $this->faker->numberBetween(10000, 5000000),
            // [west, south, east, north] in WGS84
            'bounds' => [-2.65, 51.82, -2.60, 51.85],
            'opacity' => 0.8,
            'visible_by_default' => true,
            'display_order' => 0,
        ];
    }
}
