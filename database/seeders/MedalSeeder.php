<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Medal;
use Illuminate\Database\Seeder;

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
                'description' => 'Awarded for doing Hunters\' Hole and Hunters\' Lodge Inn Sink',
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
            [
                'name' => 'Copper Miner',
                'description' => 'Awarded for caving at the Great Orme',
                'image_path' => 'copper-miner.svg',
            ],
            [
                'name' => 'Dragon\'s Lair',
                'description' => 'Awarded for 5 trips to Welsh caves',
                'image_path' => 'dragons-lair.svg',
            ],
            [
                'name' => 'Completionist',
                'description' => 'Awarded for completing any cave collection',
                'image_path' => 'completionist.svg',
            ],
            [
                'name' => 'Slate Heart',
                'description' => 'Awarded for caving in North Wales',
                'image_path' => 'slate-heart.svg',
            ],
            [
                'name' => 'Gower Power',
                'description' => 'Awarded for caving in Gower',
                'image_path' => 'gower-power.svg',
            ],
            [
                'name' => 'Free Miner',
                'description' => 'Awarded for caving in the Forest of Dean',
                'image_path' => 'free-miner.svg',
            ],
            [
                'name' => 'Archivist',
                'description' => 'Awarded for submitting a suggested edit to improve the cave data.',
                'image_path' => 'archivist.svg',
            ],
            [
                'name' => 'Cave Photographer',
                'description' => 'Awarded for adding photos to 3 of your trips.',
                'image_path' => 'cave-photographer.svg',
            ],
            [
                'name' => 'Wordsmith',
                'description' => 'Awarded for writing 5 detailed trip reports.',
                'image_path' => 'wordsmith.svg',
            ],
        ];

        foreach ($medals as $medal) {
            Medal::firstOrCreate(['name' => $medal['name']], $medal);
        }
    }
}
