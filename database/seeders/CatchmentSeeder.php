<?php

namespace Database\Seeders;

use App\Models\Catchment;
use App\Models\Cave;
use App\Models\CaveSystem;
use Illuminate\Database\Seeder;

class CatchmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Catchment with 1 gauge (Real Wookey Data)
        $mendipCatchment = Catchment::create([
            'name' => 'Upper Axe (Mampit)',
            'reference_id' => 'AXE001',
            'gauges' => [
                [
                    'name' => 'Wookey',
                    'rloi_id' => '3059'
                ]
            ]
        ]);

        // Cave System linked to 1-gauge catchment
        $swildons = CaveSystem::create([
            'name' => 'Swildons Hole',
            'slug' => 'swildons-hole',
            'length' => 9000,
            'vertical_range' => 180,
            'description' => 'A famous wet cave system on the Mendip Hills.',
            'catchment_id' => $mendipCatchment->id
        ]);
        
        Cave::factory()->create([
            'name' => 'Swildons Hole',
            'cave_system_id' => $swildons->id,
            'location_lat' => 51.239,
            'location_lng' => -2.675,
            'location_name' => 'Priddy',
            'location_country' => 'UK'
        ]);


        // 2. Catchment with 2 gauges
        $multiGaugeCatchment = Catchment::create([
            'name' => 'East Mendip Complex',
            'reference_id' => 'EM002',
            'gauges' => [
                [
                    'name' => 'Fenny Castle',
                    'rloi_id' => '3054'
                ],
                [
                    'name' => 'Shepton Mallet',
                    'rloi_id' => '9601' 
                ]
            ]
        ]);

        // Cave System linked to 2-gauge catchment
        $stokeLane = CaveSystem::create([
            'name' => 'Stoke Lane Slocker',
            'slug' => 'stoke-lane-slocker',
            'length' => 2000,
            'vertical_range' => 30,
            'description' => 'A significant swallet cave.',
            'catchment_id' => $multiGaugeCatchment->id
        ]);

        Cave::factory()->create([
            'name' => 'Stoke Lane Slocker',
            'cave_system_id' => $stokeLane->id,
            'location_lat' => 51.22,
            'location_lng' => -2.55,
            'location_name' => 'Stoke St Michael',
            'location_country' => 'UK'
        ]);


        // 3. Cave System WITHOUT Catchment
        $gbCave = CaveSystem::create([
            'name' => 'GB Cave',
            'slug' => 'gb-cave',
            'length' => 1900,
            'vertical_range' => 135,
            'description' => 'Large system on the Mendips, no river gauge linked.',
            'catchment_id' => null
        ]);

         Cave::factory()->create([
            'name' => 'GB Cave',
            'cave_system_id' => $gbCave->id,
            'location_lat' => 51.28,
            'location_lng' => -2.76,
            'location_name' => 'Charterhouse',
            'location_country' => 'UK'
        ]);

        // 4. Catchment with Rain Gauge
        $peakCatchment = Catchment::create([
            'name' => 'Derbyshire (Peak District)',
            'reference_id' => 'PEAK001',
            'gauges' => [
                [
                    'name' => 'Castleton',
                    'station_id' => '52201',
                    'type' => 'rain'
                ]
            ]
        ]);

        $titan = CaveSystem::create([
            'name' => 'Titan',
            'slug' => 'titan',
            'length' => 2000,
            'vertical_range' => 140,
            'description' => 'Deep shaft in the Peak District.',
            'catchment_id' => $peakCatchment->id
        ]);

        Cave::factory()->create([
            'name' => 'Titan',
            'cave_system_id' => $titan->id,
            'location_lat' => 53.33,
            'location_lng' => -1.78,
            'location_name' => 'Castleton',
            'location_country' => 'UK'
        ]);
    }
}
