<?php

namespace Database\Seeders;

use App\Models\CaveSystem;
use App\Models\Route;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some existing cave systems, or create if none
        $systems = CaveSystem::all();
        
        if ($systems->isEmpty()) {
            $systems = CaveSystem::factory(5)->create();
        }

        foreach ($systems as $system) {
            // Create 1-3 routes for each system
            Route::factory(rand(1, 3))
                ->for($system)
                ->hasTackle(rand(0, 5))
                ->hasMedia(rand(0, 3))
                ->create();
        }
    }
}
