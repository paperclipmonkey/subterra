<?php

declare(strict_types=1);

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

        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('test.png');

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
                'data' => $imageFile,
                'title' => 'Hero Title',
                'photographer' => 'Hero Photographer',
                'copyright' => 'Hero Copyright',
            ],
            'entrance_image' => [
                'data' => $imageFile,
                'title' => 'Entrance Title',
                'photographer' => 'Entrance Photographer',
                'copyright' => 'Entrance Copyright',
            ],
        ];

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/caves', $payload);

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

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_saves_hero_video_on_creation()
    {
        Storage::fake('media');

        // Mock the ImageProcessingService to avoid running actual FFMpeg
        $mockService = \Mockery::mock(\App\Services\ImageProcessingService::class);
        $mockService->shouldReceive('processAndStoreVideo')
            ->once()
            ->andReturn('caves/fake-video-uuid.mp4');
        $this->app->instance(\App\Services\ImageProcessingService::class, $mockService);

        // Create a data_admin user
        $user = User::factory()->create();
        $role = \App\Models\Role::firstOrCreate(['slug' => 'data_admin'], ['name' => 'Data Admin']);
        $user->roles()->attach($role);
        $user->refresh();

        $videoFile = \Illuminate\Http\UploadedFile::fake()->create('test.mp4', 100, 'video/mp4');

        $system = CaveSystem::factory()->create();

        $payload = [
            'name' => 'Cave with Video',
            'cave_system_id' => $system->id,
            'location_lat' => 52.123,
            'location_lng' => -1.123,
            'location_name' => 'Location',
            'location_country' => 'Country',
            'hero_video' => [
                'data' => $videoFile,
                'title' => 'Video Title',
                'photographer' => 'Video Shooter',
                'copyright' => 'Video Copyright',
            ],
        ];

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/caves', $payload);

        $response->assertStatus(200);

        $cave = Cave::where('name', 'Cave with Video')->first();
        $this->assertNotNull($cave);

        $this->assertDatabaseHas('cave_media', [
            'cave_id' => $cave->id,
            'type' => 'hero_video',
            'title' => 'Video Title',
            'photographer' => 'Video Shooter',
            'copyright' => 'Video Copyright',
        ]);

        $media = \App\Models\CaveMedia::where('cave_id', $cave->id)->where('type', 'hero_video')->first();
        // Since we mocked ImageProcessingService to return a generic path,
        // we can still verify the attributes are dynamically generated.
        $this->assertNotNull($media->preview_url);
        $this->assertNotNull($media->poster_url);

        \Mockery::close();
    }
}
