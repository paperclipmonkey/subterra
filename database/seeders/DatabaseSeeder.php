<?php

namespace Database\Seeders;

use App\Models\Cave;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MedalSeeder::class,
            // ...other seeders...
        ]);
    }
}
