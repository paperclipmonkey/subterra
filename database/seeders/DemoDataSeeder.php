<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Callout;
use App\Models\Incident;
use App\Models\OnCallShift;

use App\Models\Cave;
use App\Models\CalloutParticipant;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed 2 Duty Officers with upcoming shifts
        // Create 2 officers if they don't exist
            ['name' => 'Officer Alpha', 'is_active' => true]
        );
        $officer1->assignRole(['platform_admin', 'duty_officer']);

            ['name' => 'Officer Bravo', 'is_active' => true]
        );
        $officer2->assignRole(['platform_admin', 'duty_officer']);

        // Shift 0: Starts today at 9 AM (or now) for 24 hours
        OnCallShift::factory()->create([
            'user_id' => $officer1->id,
            'start_at' => now()->setTime(9, 0, 0),
            'end_at' => now()->addHours(24)->setTime(9, 0, 0),
        ]);

        // Shift 1: Starts tomorrow at 9 AM for 24 hours
        OnCallShift::factory()->create([
            'user_id' => $officer2->id,
            'start_at' => now()->addDay()->setTime(9, 0, 0),
            'end_at' => now()->addDay()->addHours(24)->setTime(9, 0, 0),
        ]);

        // Shift 2: Starts the day after tomorrow at 9 AM for 24 hours
        OnCallShift::factory()->create([
            'user_id' => $officer1->id,
            'start_at' => now()->addDays(2)->setTime(9, 0, 0),
            'end_at' => now()->addDays(2)->addHours(24)->setTime(9, 0, 0),
        ]);

        $this->command->info('Seeded 2 Duty Officers with shifts today and upcoming.');

        // 2. Seed a few Open Callouts
        // Ensure we have a cave
        $cave = Cave::inRandomOrder()->first();
        if (!$cave) {
            $cave = Cave::factory()->create();
        }

        // Create some users for the callouts
        $users = User::factory()->count(3)->create();

        foreach ($users as $index => $user) {
            $callout = Callout::factory()->create([
                'user_id' => $user->id,
                'cave_id' => $cave->id,
                'status' => 'active', // "open" callouts are loosely defined, usually 'active' means the trip is ongoing
                'callout_time' => now()->addHours($index + 2), // Should be back in a few hours
                'description' => "Trip description for " . $user->name,
                'trip_plan' => 'Visiting the main streamway and high level chambers.',
                'car_details' => 'Silver Volvo Estate',
                'car_registration' => 'AB' . ($index + 10) . ' CDE',
                'car_parking' => 'Parked in the farmer\'s field by the gate.',
                'team_details' => 'Party of 4. Experienced. 2 First Aiders.',
            ]);

            // Add the main user as a participant
            CalloutParticipant::create([
                'callout_id' => $callout->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => '07700 80000' . $index,
                'email' => $user->email,
            ]);

            // Add 1 extra participant
            CalloutParticipant::create([
                'callout_id' => $callout->id,
                'name' => 'Participant Part ' . $index,
                'phone' => '07700 70000' . $index,
            ]);
        }
        
        $this->command->info('Seeded 3 Open Callouts.');

        // 3. Seed 1 Active Incident
        // An active incident requires an overdue callout
        $victim = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'victim@example.com'],
            ['name' => 'Victim Victor', 'is_active' => true]
        );

        $incidentCallout = Callout::factory()->create([
            'user_id' => $victim->id,
            'cave_id' => $cave->id,
            'status' => 'triggered', // Incident triggered 
            'callout_time' => now()->subHours(2), // Overdue by 2 hours
            'description' => 'Went for a quick trip, not back yet.',
            'trip_plan' => 'Through trip to the lower entrance.',
            'car_details' => 'Red Ford Fiesta',
            'car_registration' => 'XY99 ZZZ',
            'car_parking' => 'Layby on the main road.',
            'team_details' => 'Solo caver. Carrying basic emergency kit.',
        ]);

        // Add victim as participant
        CalloutParticipant::create([
            'callout_id' => $incidentCallout->id,
            'user_id' => $victim->id,
            'name' => $victim->name,
            'phone' => '07700 111222',
            'email' => $victim->email,
        ]);

        // Add a second participant
        CalloutParticipant::create([
            'callout_id' => $incidentCallout->id,
            'name' => 'Companion Chris',
            'phone' => '07700 333444',
        ]);

        Incident::factory()->create([
            'callout_id' => $incidentCallout->id,
            'status' => 'open', // Active incident
        ]);

        // Ensure the incident cave has a region tag
        $regionTag = \App\Models\Tag::where('category', 'region')->inRandomOrder()->first();
        if (!$regionTag) {
            $regionTag = \App\Models\Tag::factory()->create([
                'category' => 'region',
                'tag' => 'Mendip'
            ]);
        }
        $incidentCallout->cave->tags()->syncWithoutDetaching([$regionTag->id]);

        $this->command->info("Seeded 1 Active Incident for user Victim Victor in cave {$incidentCallout->cave->name} with region {$regionTag->tag}.");
    }
}
