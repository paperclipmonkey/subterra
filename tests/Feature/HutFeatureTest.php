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

    public function test_can_list_huts(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        Hut::factory()->count(3)->create(['club_id' => $club->id]);

        $response = $this->actingAs($user)->getJson('/api/huts');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_can_view_hut_details_with_nearby_caves(): void
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

    public function test_can_create_hut(): void
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

    public function test_can_create_hut_without_club(): void
    {
        $user = User::factory()->create();

        $hutData = [
            'name' => 'Independent Hut',
            'description' => 'A hut without a club',
            'external_url' => 'https://example.com/',
            'location_lat' => 51.0,
            'location_lng' => -2.5,
            'club_id' => null,
        ];

        $response = $this->actingAs($user)->postJson('/api/huts', $hutData);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Independent Hut')
            ->assertJsonPath('club_id', null);

        $this->assertDatabaseHas('huts', [
            'name' => 'Independent Hut',
            'club_id' => null,
        ]);
    }

    public function test_can_create_hut_with_image(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();

        // Create a simple base64 encoded 1x1 pixel PNG
        $imageData = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        
        $hutData = [
            'name' => 'Test Hut',
            'description' => 'A test hut with image',
            'club_id' => $club->id,
            'image' => [
                'data' => 'data:image/png;base64,' . $imageData,
            ]
        ];

        $response = $this->actingAs($user)->postJson('/api/huts', $hutData);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Test Hut');

        $hut = Hut::where('name', 'Test Hut')->first();
        $this->assertNotNull($hut);
        $this->assertNotNull($hut->image);
        $this->assertStringContainsString('huts/', $hut->image);
        $this->assertStringEndsWith('.webp', $hut->image);
    }

    public function test_can_update_hut(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        $hut = Hut::factory()->create([
            'club_id' => $club->id,
            'name' => 'Original Name',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'club_id' => $club->id,
            'location_lat' => 51.251708,
            'location_lng' => -2.657503,
        ];

        $response = $this->actingAs($user)->putJson("/api/huts/{$hut->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Updated Name')
            ->assertJsonPath('description', 'Updated description');

        $this->assertDatabaseHas('huts', [
            'id' => $hut->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_update_hut_with_image(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        $hut = Hut::factory()->create([
            'club_id' => $club->id,
        ]);

        // Create a simple base64 encoded 1x1 pixel PNG
        $imageData = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        
        $updateData = [
            'name' => $hut->name,
            'club_id' => $club->id,
            'image' => [
                'data' => 'data:image/png;base64,' . $imageData,
            ]
        ];

        $response = $this->actingAs($user)->putJson("/api/huts/{$hut->id}", $updateData);

        $response->assertStatus(200);

        $hut->refresh();
        $this->assertNotNull($hut->image);
        $this->assertStringEndsWith('.webp', $hut->image);
    }

    public function test_can_delete_hut(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        $hut = Hut::factory()->create([
            'club_id' => $club->id,
            'name' => 'To Be Deleted',
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/huts/{$hut->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('huts', [
            'id' => $hut->id,
        ]);
    }

    public function test_can_manage_reciprocal_clubs(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        $reciprocalClub1 = Club::factory()->create();
        $reciprocalClub2 = Club::factory()->create();

        $hutData = [
            'name' => 'Reciprocal Hut',
            'club_id' => $club->id,
            'reciprocal_clubs' => [$reciprocalClub1->id, $reciprocalClub2->id],
        ];

        // Create
        $response = $this->actingAs($user)->postJson('/api/huts', $hutData);

        $response->assertStatus(201);
        
        $hut = Hut::where('name', 'Reciprocal Hut')->first();
        $this->assertCount(2, $hut->reciprocalClubs);
        $this->assertTrue($hut->reciprocalClubs->contains($reciprocalClub1));
        $this->assertTrue($hut->reciprocalClubs->contains($reciprocalClub2));

        // Update
        $updateData = [
            'name' => 'Reciprocal Hut',
            'club_id' => $club->id,
            'reciprocal_clubs' => [$reciprocalClub1->id], // Remove one
        ];

        $response = $this->actingAs($user)->putJson("/api/huts/{$hut->id}", $updateData);
        $response->assertStatus(200);

        $hut->refresh();
        $this->assertCount(1, $hut->reciprocalClubs);
        $this->assertTrue($hut->reciprocalClubs->contains($reciprocalClub1));
        $this->assertFalse($hut->reciprocalClubs->contains($reciprocalClub2));
    }
}
