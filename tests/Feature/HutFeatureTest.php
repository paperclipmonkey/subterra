<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Hut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HutFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_huts_filters_those_without_clubs(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();

        // Huts associated with a club
        Hut::factory()->count(3)->create(['club_id' => $club->id]);

        // Hut NOT associated with a club (should be filtered out)
        Hut::factory()->create(['club_id' => null]);

        $response = $this->actingAs($user)->getJson('/api/huts');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_can_create_hut(): void
    {
        $user = User::factory()->admin()->create();
        $club = Club::factory()->create();

        $hutData = [
            'name' => 'The Belfy',
            'description' => 'A great hut',
            'external_url' => 'https://bec-cave.org.uk/',
            'booking_info' => "Contact the booking secretary\n",
            'location_lat' => 51.251708,
            'location_lng' => -2.657503,
            'club_id' => $club->id,
            'amenities' => ['Showers, two bunk rooms, kitchen, log fire'],
        ];

        $response = $this->actingAs($user)->postJson('/api/huts', $hutData);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'The Belfy')
            ->assertJsonPath('club_id', $club->id)
            ->assertJsonPath('amenities', ['Showers, two bunk rooms, kitchen, log fire']);

        $this->assertDatabaseHas('huts', [
            'name' => 'The Belfy',
            'club_id' => $club->id,
        ]);
    }

    public function test_can_create_hut_without_club(): void
    {
        $user = User::factory()->admin()->create();

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
        $user = User::factory()->admin()->create();
        $club = Club::factory()->create();

        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('test.png');

        $hutData = [
            'name' => 'Test Hut',
            'description' => 'A test hut with image',
            'club_id' => $club->id,
            'image' => [
                'data' => $imageFile,
            ],
        ];

        $response = $this->actingAs($user)->withHeaders(['Accept' => 'application/json'])->post('/api/huts', $hutData);

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
        $user = User::factory()->admin()->create();
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
        $user = User::factory()->admin()->create();
        $club = Club::factory()->create();
        $hut = Hut::factory()->create([
            'club_id' => $club->id,
        ]);

        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('test.png');

        $updateData = [
            'name' => $hut->name,
            'club_id' => $club->id,
            'image' => [
                'data' => $imageFile,
            ],
            '_method' => 'PUT',
        ];

        $response = $this->actingAs($user)->withHeaders(['Accept' => 'application/json'])->post("/api/huts/{$hut->id}", $updateData);

        $response->assertStatus(200);

        $hut->refresh();
        $this->assertNotNull($hut->image);
        $this->assertStringEndsWith('.webp', $hut->image);
    }

    public function test_can_delete_hut(): void
    {
        $user = User::factory()->admin()->create();
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
        $user = User::factory()->admin()->create();
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

    public function test_can_create_hut_with_base64_image(): void
    {
        $user = User::factory()->admin()->create();
        $club = Club::factory()->create();

        // Create a small valid PNG as base64 data URI
        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('test.png', 10, 10);
        $base64 = 'data:image/png;base64,'.base64_encode(file_get_contents($imageFile->getPathname()));

        $hutData = [
            'name' => 'Base64 Hut',
            'description' => 'A test hut with base64 image',
            'club_id' => $club->id,
            'image' => [
                'data' => $base64,
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/huts', $hutData);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Base64 Hut');

        $hut = Hut::where('name', 'Base64 Hut')->first();
        $this->assertNotNull($hut);
        $this->assertNotNull($hut->image);
        $this->assertStringContainsString('huts/', $hut->image);
        $this->assertStringEndsWith('.webp', $hut->image);
    }

    public function test_can_update_hut_with_base64_image(): void
    {
        $user = User::factory()->admin()->create();
        $club = Club::factory()->create();
        $hut = Hut::factory()->create([
            'club_id' => $club->id,
        ]);

        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('test.png', 10, 10);
        $base64 = 'data:image/png;base64,'.base64_encode(file_get_contents($imageFile->getPathname()));

        $updateData = [
            'name' => $hut->name,
            'club_id' => $club->id,
            'image' => [
                'data' => $base64,
            ],
        ];

        $response = $this->actingAs($user)->putJson("/api/huts/{$hut->id}", $updateData);

        $response->assertStatus(200);

        $hut->refresh();
        $this->assertNotNull($hut->image);
        $this->assertStringEndsWith('.webp', $hut->image);
    }

    public function test_image_field_hidden_from_json(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        Hut::factory()->create([
            'club_id' => $club->id,
            'image' => 'huts/test.webp',
        ]);

        $response = $this->actingAs($user)->getJson('/api/huts');

        $response->assertStatus(200);
        $response->assertJsonMissing(['image' => 'huts/test.webp']);
        $response->assertJsonFragment(['image_url' => \Illuminate\Support\Facades\Storage::disk('media')->url('huts/test.webp')]);
    }
}
