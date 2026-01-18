<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Trip;
use App\Models\User;
use App\Models\TripUser;
use App\Models\TripMedia;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create a test user
        // Create or find a test user
        $user = User::firstOrCreate(
            ['email' => 'testuser@example.com'],
            ['name' => 'Test User', 'is_active' => true, 'is_approved' => true]
        );

        // Ensure user is in a club
        $club = \App\Models\Club::first(); 
        if ($club) {
             $user->clubs()->syncWithoutDetaching([
                 $club->id => ['is_admin' => true, 'status' => 'approved']
             ]);
        }

        // Create a test trip
        $trip = Trip::factory()->create([
            'name' => 'Test Trip',
            'description' => 'A test trip for seeding data.',
        ]);

        // Create a test trip media
        TripMedia::factory()->create([
            'trip_id' => $trip->id,
            'filename' => 'test_image.jpg',
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
                'is_official' => true,
            ]
        );

        if ($caves->count() > 0) {
            $collection->caves()->attach($caves->pluck('id'));
        }

        $collection2 = \App\Models\Collection::firstOrCreate(
            ['slug' => 'vertical-challenges'],
            [
                'user_id' => $user->id,
                'name' => 'Vertical Challenges',
                'description' => 'Caves that require SRT skills.',
                'is_official' => false,
            ]
        );
        
        if ($caves->count() > 2) {
            $collection2->caves()->attach($caves->random(2)->pluck('id'));
        }
    }
}
