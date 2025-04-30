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
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'testuser@example.com',
        ]);

        // Create a test trip
        $trip = Trip::factory()->create([
            'name' => 'Test Trip',
            'description' => 'A test trip for seeding data.',
        ]);

        // Create a test trip user
        TripUser::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
        ]);

        // Create a test trip media
        TripMedia::factory()->create([
            'trip_id' => $trip->id,
            'filename' => 'test_image.jpg',
        ]);
    }
}
