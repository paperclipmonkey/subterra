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
                'image_path' => 'first-trip.svg',
            ],
            [
                'name' => 'Explorer',
                'description' => 'Awarded for visiting 5 different caves.',
                'image_path' => 'explorer.svg',
            ],
            [
                'name' => 'Veteran',
                'description' => 'Awarded for participating in 20 trips.',
                'image_path' => 'veteran.svg',
            ],
            [
                'name' => 'Night Owl',
                'description' => 'Awarded for a trip that started after 8pm.',
                'image_path' => 'night-owl.svg',
            ],
            [
                'name' => 'Through Trip',
                'description' => 'Awarded for a trip where entrance and exit caves are different.',
                'image_path' => 'through-trip.svg',
            ],
            [
                'name' => 'Ham pasta aficionado',
                'description' => 'Awarded for doing Hunters Hole and Hunters Lodge Inn Sink',
                'image_path' => 'ham-pasta.svg',
            ],
            [
                'name' => 'Hard Caver',
                'description' => 'Awarded for trips in Yorkshire, Mendip and Wales',
                'image_path' => 'hard-caver.svg',
            ],
            [
                'name' => 'History Buff',
                'description' => 'Awarded for doing 5 mines',
                'image_path' => 'history-buff.svg',
            ],
            [
                'name' => 'Sport Climber',
                'description' => 'Awarded for caving in Portland',
                'image_path' => 'sport-climber.svg',
            ],
            [
                'name' => 'Cream Tea',
                'description' => 'Awarded for caving in Devon',
                'image_path' => 'cream-tea.svg',
            ],
            [
                'name' => 'Highland Cow',
                'description' => 'Awarded for caving in Scotland',
                'image_path' => 'highland-cow.svg',
            ],
            [
                'name' => 'Sheep dog',
                'description' => 'Awarded for going on 5 trips to leader systems',
                'image_path' => 'sheep-dog.svg',
            ],
            [
                'name' => 'Mucky Pup',
                'description' => 'Awarded for going to 3 muddy caves',
                'image_path' => 'mucky-pup.svg',
            ],
            [
                'name' => 'Faff Now Cave Later',
                'description' => 'For 5 trips to SWCC caves',
                'image_path' => 'faff-now-cave-later.svg',
            ],
            [
                'name' => 'String Dangler',
                'description' => 'For 10 trips to SRT caves',
                'image_path' => 'string-dangler.svg',
            ],
            // [
            //     'name' => 'Long Trip',
            //     'description' => 'Awarded for a trip that lasted more than 8 hours.',
            //     'image_path' => 'long-trip.svg',
            // ],
            // [
            //     'name' => 'Wet Trip',
            //     'description' => 'Awarded for a trip where you got wet.',
            //     'image_path' => 'wet-trip.svg',
            // ],
            // [
            //     'name' => 'Mud Monster',
            //     'description' => 'Awarded for a trip where you got muddy.',
            //     'image_path' => 'mud-monster.svg',
            // ],
            // [
            //     'name' => 'SRT Master',
            //     'description' => 'Awarded for a trip that involved SRT.',
            //     'image_path' => 'srt-master.svg',
            // ],
            // [
            //     'name' => 'Caving Legend',
            //     'description' => 'Awarded for completing 100 trips.',
            //     'image_path' => 'caving-legend.svg',
            // ],
        ];

        foreach ($medals as $medal) {
            Medal::firstOrCreate(['name' => $medal['name']], $medal);
        }
    }
}
