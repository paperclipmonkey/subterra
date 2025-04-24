<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Medal;

class MedalSeeder extends Seeder
{
    public function run(): void
    {
        $medals = [
            [
                'name' => 'First Trip',
                'description' => 'Awarded for completing your first trip.',
                'image_path' => 'first-trip.png',
            ],
            [
                'name' => 'Explorer',
                'description' => 'Awarded for visiting 5 different caves.',
                'image_path' => 'explorer.png',
            ],
            [
                'name' => 'Veteran',
                'description' => 'Awarded for participating in 20 trips.',
                'image_path' => 'veteran.png',
            ],
            [
                'name' => 'Night Owl',
                'description' => 'Awarded for a trip that started after 8pm.',
                'image_path' => 'night-owl.png',
            ],
            [
                'name' => 'Through Trip',
                'description' => 'Awarded for a trip where entrance and exit caves are different.',
                'image_path' => 'through-trip.png',
            ],
        ];

        foreach ($medals as $medal) {
            Medal::firstOrCreate(['name' => $medal['name']], $medal);
        }
    }
}
