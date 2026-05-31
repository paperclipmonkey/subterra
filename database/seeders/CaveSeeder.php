<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cave;
use App\Models\CaveSystem;
use Illuminate\Database\Seeder;

class CaveSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $curatedTag = \App\Models\Tag::where('tag', 'Curated')->where('category', 'curated')->first();
        $closedTag = \App\Models\Tag::where('tag', 'Closed')->first();

        // --- Curated caves (well-documented, worth visiting) ---

        Cave::factory()->count(3)->create()->each(function (Cave $cave) use ($curatedTag) {
            if ($curatedTag) {
                $cave->tags()->attach($curatedTag->id);
            }
        });

        // Create a cave system with multiple named caves
        $caveSystem = CaveSystem::factory()->create();

        for ($i = 0; $i < 3; ++$i) {
            $description = 'Description for Cave '.($i + 1);
            if ($i === 0) {
                $description .= "\n\n### Cave Survey Diagram\n\n```mermaid\ngraph TD\n    A[Entrance] --> B[Main Chamber]\n    B --> C[Streamway]\n    B --> D[Upper Series]\n    C --> E[Sump]\n```";
            }
            $cave = Cave::factory()->create([
                'cave_system_id' => $caveSystem->id,
                'name' => 'Cave '.($i + 1),
                'description' => $description,
            ]);
            if ($curatedTag) {
                $cave->tags()->attach($curatedTag->id);
            }
        }

        // Closed cave — curated but tagged closed
        $closedCave = Cave::factory()->create([
            'name' => 'Closed Cave',
            'description' => 'This cave is closed for access.',
            'cave_system_id' => $caveSystem->id,
        ]);
        $tagIds = array_filter([$curatedTag?->id, $closedTag?->id]);
        if ($tagIds) {
            $closedCave->tags()->attach($tagIds);
        }

        // --- Non-curated caves (smaller / less notable systems, ~2000) ---
        // Created in batches with shared cave systems for realistic structure.

        $batchSize = 10; // caves per system
        $totalNonCurated = 2000;

        for ($batch = 0; $batch < $totalNonCurated / $batchSize; ++$batch) {
            $system = CaveSystem::factory()->create();
            Cave::factory()->count($batchSize)->create(['cave_system_id' => $system->id]);
            // No curated tag — these are intentionally omitted from the curated view
        }
    }
}
