<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cave;
use App\Models\Club;
use App\Models\Trip;
use App\Models\TripMedia;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds joint trips between Active Club and a couple of allied clubs so the club
 * page's "Caved Alongside" section (and the recent-trips / photo-wall sections)
 * have data to show out of the box.
 */
class CrossClubTripSeeder extends Seeder
{
    public function run(): void
    {
        $activeClub = Club::where('name', 'Active Club')->first();
        if (!$activeClub) {
            return;
        }

        // An approved Active Club member to host the joint trips.
        $host = $activeClub->approvedUsers()->orderBy('users.id')->first();
        if (!$host) {
            return;
        }

        $cave = Cave::query()->first() ?? Cave::factory()->create();

        // Each allied club shares a number of recent trips with Active Club.
        $alliedClubs = [
            ['name' => 'Craven Pothole Club', 'guest' => 'Cora Craven', 'email' => 'craven.guest@subterra.test', 'trips' => 2],
            ['name' => 'Bradford Pothole Club', 'guest' => 'Bryn Bradford', 'email' => 'bradford.guest@subterra.test', 'trips' => 1],
        ];

        foreach ($alliedClubs as $allied) {
            $club = Club::firstOrCreate(
                ['slug' => Str::slug($allied['name'])],
                [
                    'name' => $allied['name'],
                    'description' => "{$allied['name']} caves regularly with neighbouring clubs.",
                    'is_active' => true,
                ]
            );

            $guest = User::firstOrCreate(
                ['email' => $allied['email']],
                ['name' => $allied['guest'], 'is_active' => true]
            );
            $guest->clubs()->syncWithoutDetaching([
                $club->id => ['is_admin' => false, 'status' => 'approved'],
            ]);

            for ($i = 1; $i <= $allied['trips']; ++$i) {
                $day = 4 + $i;
                $trip = Trip::firstOrCreate(
                    ['name' => "Joint meet with {$allied['name']} ({$i})"],
                    [
                        'description' => "A joint trip between Active Club and {$allied['name']}.",
                        'entrance_cave_id' => $cave->id,
                        'exit_cave_id' => $cave->id,
                        'cave_system_id' => $cave->cave_system_id,
                        'visibility' => 'club',
                        'start_time' => Carbon::now()->subDays($day)->setTime(9, 0),
                        'end_time' => Carbon::now()->subDays($day)->setTime(14, 0),
                    ]
                );

                $trip->participants()->syncWithoutDetaching([$host->id, $guest->id]);

                TripMedia::firstOrCreate(
                    ['trip_id' => $trip->id, 'filename' => 'joint_meet_'.Str::slug($allied['name'])."_{$i}.jpg"],
                    [
                        'title' => "Underground with {$allied['name']}",
                        'photographer' => $allied['guest'],
                        'copyright' => $allied['name'],
                        'taken_at' => Carbon::now()->subDays($day)->setTime(12, 0),
                    ]
                );
            }
        }

        $this->command->info('Seeded joint trips with 2 allied clubs for the club page.');
    }
}
