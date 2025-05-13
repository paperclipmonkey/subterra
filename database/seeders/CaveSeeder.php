<?php

namespace Database\Seeders;

use App\Models\Cave;
use Illuminate\Database\Seeder;

class CaveSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Cave::factory()->count(3)->create();
    }
}
