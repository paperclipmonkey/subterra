<?php

namespace Database\Seeders;

use App\Models\Club;
use Illuminate\Database\Seeder;

class ClubSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Club::factory()->enabled()->create([
            'name' => 'Active Club',
            'description' => 'This club is active and open for new members.',
        ]);

        Club::factory()->disabled()->create([
            'name' => 'Disabled Club',
            'description' => 'This club is disabled for testing purposes.',
        ]);
    }
}
