<?php

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaveCreationTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_saves_all_cave_data_on_creation()
    {
        Storage::fake('media');

        // Create a data_admin user
        $user = User::factory()->create();
        $role = \App\Models\Role::firstOrCreate(['slug' => 'data_admin'], ['name' => 'Data Admin']);
        $user->roles()->attach($role);
        $user->refresh();

        // Create a cave system
        $system = CaveSystem::factory()->create();

        // Create a tag
        $tag = Tag::factory()->create(['category' => 'type', 'tag' => 'wet', 'assignable' => true]);

        // Mock image data (base64 simulation as used in controller)
        // Controller expects array with 'data' key containing base64 string
        $imageBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $payload = [
            'name' => 'New Cave',
            'description' => 'A description',
            'cave_system_id' => $system->id,
            'slug' => 'custom-slug-for-caves',
            'location_lat' => 52.123,
            'location_lng' => -1.123,
            'location_name' => 'Location',
            'location_country' => 'Country',
            'tags' => [
                [
                    'category' => 'type',
                    'tag' => 'wet',
                ],
            ],
            'hero_image' => [
                'data' => $imageBase64,
                'title' => 'Hero Title',
                'photographer' => 'Hero Photographer',
                'copyright' => 'Hero Copyright',
            ],
            'entrance_image' => [
                'data' => $imageBase64,
                'title' => 'Entrance Title',
                'photographer' => 'Entrance Photographer',
                'copyright' => 'Entrance Copyright',
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/caves', $payload);

        $response->assertStatus(200);

        $cave = Cave::where('name', 'New Cave')->first();
        $this->assertNotNull($cave);

        // 1. Verify Slug
        $this->assertEquals('custom-slug-for-caves', $cave->slug, 'Slug should be saved from payload');

        // 2. Verify Tags
        $this->assertTrue($cave->tags->contains($tag), 'Tags should be synced');

        // 3. Verify Hero Image
        $this->assertDatabaseHas('cave_media', [
            'cave_id' => $cave->id,
            'type' => 'hero',
            'title' => 'Hero Title',
            'photographer' => 'Hero Photographer',
            'copyright' => 'Hero Copyright',
        ]);

        // 4. Verify Entrance Image
        $this->assertDatabaseHas('cave_media', [
            'cave_id' => $cave->id,
            'type' => 'entrance',
            'title' => 'Entrance Title',
            'photographer' => 'Entrance Photographer',
            'copyright' => 'Entrance Copyright',
        ]);

        // Verify System ID
        $this->assertEquals($system->id, $cave->cave_system_id);
    }
}
