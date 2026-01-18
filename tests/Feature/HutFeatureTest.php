<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Hut;
use App\Models\Club;
use App\Models\Cave;

class HutFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_huts()
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        Hut::factory()->count(3)->create(['club_id' => $club->id]);

        $response = $this->actingAs($user)->getJson('/api/huts');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_can_view_hut_details_with_nearby_caves()
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        $hut = Hut::factory()->create([
            'club_id' => $club->id,
            'location_lat' => 50.0000,
            'location_lng' => -2.0000,
        ]);

        // Cave nearby (approx 1km away)
        // 1 deg lat is approx 111km. 0.01 is 1.1km.
        $nearbyCave = Cave::factory()->create([
            'location_lat' => 50.0090, 
            'location_lng' => -2.0000,
        ]);

        // Cave far away
        $farCave = Cave::factory()->create([
            'location_lat' => 51.0000,
            'location_lng' => -2.0000,
        ]);

        $response = $this->actingAs($user)->getJson("/api/huts/{$hut->id}");

        $response->assertStatus(200)
            ->assertJsonPath('id', $hut->id)
            ->assertJsonPath('name', $hut->name);
        
        $caves = $response->json('nearby_caves');
        
        if (\DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite does not support the complex distance calculation SQL functions used.');
        } else {
            $this->assertCount(1, $caves);
            $this->assertEquals($nearbyCave->id, $caves[0]['id']);
        }
    }

    public function test_can_create_hut()
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();

        $hutData = [
            'name' => 'The Belfy',
            'description' => 'A great hut',
            'external_url' => 'https://bec-cave.org.uk/',
            'booking_info' => "Contact the booking secretary\n",
            'location_lat' => 51.251708,
            'location_lng' => -2.657503,
            'club_id' => $club->id,
            'amenities' => ["Showers, two bunk rooms, kitchen, log fire"]
        ];

        $response = $this->actingAs($user)->postJson('/api/huts', $hutData);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'The Belfy')
            ->assertJsonPath('club_id', $club->id)
            ->assertJsonPath('amenities', ["Showers, two bunk rooms, kitchen, log fire"]);

        $this->assertDatabaseHas('huts', [
            'name' => 'The Belfy',
            'club_id' => $club->id,
        ]);
    }
}
