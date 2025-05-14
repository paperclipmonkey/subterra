<?php

namespace Database\Seeders;

use App\Models\Cave;
use Illuminate\Database\Seeder;
use Database\Seeders\TagSeeder; // Add this use statement

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MedalSeeder::class,
            ClubSeeder::class,
            TagSeeder::class,
            CaveSeeder::class,
        ]);
    }
}
