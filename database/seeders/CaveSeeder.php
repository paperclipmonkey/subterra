<?php

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
        Cave::factory()->count(3)->create();

        // Create a cave system with multiple caves
        $caveSystem = CaveSystem::factory()->create();

        // Create multiple caves within the cave system
        for ($i = 0; $i < 3; ++$i) {
            $description = 'Description for Cave '.($i + 1);
            if ($i === 0) {
                $description .= "\n\n### Cave Survey Diagram\n\n```mermaid\ngraph TD\n    A[Entrance] --> B[Main Chamber]\n    B --> C[Streamway]\n    B --> D[Upper Series]\n    C --> E[Sump]\n```";
            }
            Cave::factory()->create([
                'cave_system_id' => $caveSystem->id,
                'name' => 'Cave '.($i + 1),
                'description' => $description,
            ]);
        }

        // Create a Closed Cave
        $closedTag = \App\Models\Tag::where('tag', 'Closed')->first();
        if ($closedTag) {
            $closedCave = Cave::factory()->create([
                'name' => 'Closed Cave',
                'description' => 'This cave is closed for access.',
                'cave_system_id' => $caveSystem->id,
            ]);
            $closedCave->tags()->attach($closedTag);
        }
    }
}
