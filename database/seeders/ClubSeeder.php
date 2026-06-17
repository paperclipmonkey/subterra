<?php

declare(strict_types=1);

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

        // The "Direct Individual Member" catch-all club for cavers who aren't
        // part of a real club. Identified by its slug; it has no member roster,
        // club trips or stats.
        Club::factory()->enabled()->create([
            'name' => 'Direct Individual Member',
            'slug' => Club::SLUG_DIRECT_INDIVIDUAL,
            'description' => 'For cavers who hold a direct individual membership rather than belonging to a club.',
        ]);
    }
}
