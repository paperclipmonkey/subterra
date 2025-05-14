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
        for ($i = 0; $i < 3; $i++) {
            Cave::factory()->create([
                'cave_system_id' => $caveSystem->id,
                'name' => 'Cave ' . ($i + 1),
                'description' => 'Description for Cave ' . ($i + 1),
            ]);
        }
    }
}
