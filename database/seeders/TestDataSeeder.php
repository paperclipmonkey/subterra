<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Trip;
use App\Models\TripMedia;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create a test user
        // Create or find a test user
        $user = User::firstOrCreate(
            ['email' => 'testuser@example.com'],
            ['name' => 'Test User', 'is_active' => true]
        );

        // Ensure user is in a club
        $club = \App\Models\Club::first();
        if ($club) {
            $user->clubs()->syncWithoutDetaching([
                $club->id => ['is_admin' => true, 'status' => 'approved'],
            ]);
        }

        // Create a specific test cave
        $cave = \App\Models\Cave::factory()->create([
            'name' => 'Media Test Cave',
            'description' => 'A specific cave created for testing media and trips.',
        ]);

        // Create a test trip linked to this cave
        $trip = Trip::factory()->create([
            'name' => 'Media Test Trip',
            'description' => 'A test trip for seeding data.',
            'entrance_cave_id' => $cave->id,
            'exit_cave_id' => $cave->id,
            'cave_system_id' => $cave->cave_system_id,
            'visibility' => 'public',
        ]);

        $trip->participants()->attach($user->id);

        // Create a test trip media
        TripMedia::factory()->create([
            'trip_id' => $trip->id,
            'filename' => 'test_image.jpg',
            'title' => 'Entrance Passage',
            'copyright' => 'Subterra Club',
            'photographer' => 'Test User',
            'taken_at' => now()->subDays(2),
        ]);

        TripMedia::factory()->create([
            'trip_id' => $trip->id,
            'filename' => 'test_image_2.jpg',
            'title' => 'Main Chamber',
            'copyright' => 'Subterra Club',
            'photographer' => 'Test User',
            'taken_at' => now()->subDays(2)->addHours(2),
        ]);

        // --- Huts Seeding ---
        $club = \App\Models\Club::first();
        if ($club) {
            \App\Models\Hut::firstOrCreate(
                ['name' => 'The Caving Shed', 'club_id' => $club->id],
                [
                    'description' => 'A cozy shed near the main cave entrance. Has basic amenities.',
                    'location_lat' => 54.1234,
                    'location_lng' => -2.5678,
                    'amenities' => ['Electricity', 'Water', 'Stove'],
                    'external_url' => 'https://example.com/shed',
                    'booking_info' => 'Contact the club secretary.',
                ]
            );

            \App\Models\Hut::firstOrCreate(
                ['name' => 'Mountain Lodge', 'club_id' => $club->id],
                [
                    'description' => 'A remote lodge higher up the mountain. Great views.',
                    'location_lat' => 54.2345,
                    'location_lng' => -2.6789,
                    'amenities' => ['Fireplace', 'Bunks'],
                    'booking_info' => 'Key in lockbox.',
                ]
            );
        }

        // --- Collections Seeding ---
        $caves = \App\Models\Cave::limit(5)->get();

        $collection = \App\Models\Collection::firstOrCreate(
            ['slug' => 'top-5-beginner-caves'], // Use slug as unique identifier logic here if preferred, or keep searching by name/user
            [
                'user_id' => $user->id,
                'name' => 'Top 5 Beginner Caves',
                'description' => 'A curated list of caves perfect for beginners.',
            ]
        );

        if ($caves->count() > 0) {
            $collection->caves()->syncWithoutDetaching($caves->pluck('id'));
        }

        $collection2 = \App\Models\Collection::firstOrCreate(
            ['slug' => 'vertical-challenges'],
            [
                'user_id' => $user->id,
                'name' => 'Vertical Challenges',
                'description' => 'Caves that require SRT skills.',
            ]
        );

        if ($caves->count() > 2) {
            $collection2->caves()->syncWithoutDetaching($caves->random(2)->pluck('id'));
        }
    }
}
