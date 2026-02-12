<?php

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_saves_media_metadata_when_creating_trip()
    {
        $user = User::factory()->create();
        $entrance = Cave::factory()->create();

        $tripData = [
            'name' => 'Metadata Test Trip',
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-02 10:00:00',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'description' => 'Test description',
            'participants' => [$user->id],
            'visibility' => 'public',
            'media' => [
                [
                    'data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=',
                    'filename' => 'test_image.png',
                    'title' => 'My Awesome Photo',
                    'photographer' => 'Photographer Name',
                    'copyright' => '© 2024 Studio',
                    'taken_at' => '2024-01-01 12:00:00',
                ],
            ],
        ];

        $this->actingAs($user);
        $response = $this->postJson('/api/trips', $tripData);

        $response->assertCreated();

        $trip = Trip::where('name', 'Metadata Test Trip')->first();
        $this->assertNotNull($trip, 'Trip was not created');

        $media = $trip->media->first();
        $this->assertNotNull($media, 'Media was not created');

        $this->assertEquals('My Awesome Photo', $media->title, 'Title match failed');
        $this->assertEquals('Photographer Name', $media->photographer, 'Photographer match failed');
        $this->assertEquals('© 2024 Studio', $media->copyright, 'Copyright match failed');

        // Check taken_at if possible (might need formatting check)
        // $this->assertEquals('2024-01-01 12:00:00', $media->taken_at->format('Y-m-d H:i:s'));

        $media = $trip->media->first();
        $this->assertNotNull($media, 'Media was not created');

        $this->assertEquals('My Awesome Photo', $media->title, 'Title match failed');
        $this->assertEquals('Photographer Name', $media->photographer, 'Photographer match failed');
        $this->assertEquals('© 2024 Studio', $media->copyright, 'Copyright match failed');

        // Check taken_at if possible (might need formatting check)
        // $this->assertEquals('2024-01-01 12:00:00', $media->taken_at->format('Y-m-d H:i:s'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_saves_media_metadata_when_updating_trip()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $trip->participants()->attach($user);

        $updateData = [
            'name' => 'Updated Trip',
            'cave_system_id' => $trip->cave_system_id,
            'entrance_cave_id' => $trip->entrance_cave_id,
            'exit_cave_id' => $trip->exit_cave_id,
            'description' => 'Updated description',
            'participants' => [$user->id],
            'media' => [
                [
                    'data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=',
                    'filename' => 'new_image.png',
                    'title' => 'New Awesome Photo',
                    'photographer' => 'New Photographer',
                    'copyright' => '© 2024 New Studio',
                    'taken_at' => '2024-02-01 12:00:00',
                ],
            ],
        ];

        $this->actingAs($user);
        $response = $this->putJson('/api/trips/'.$trip->short_id, $updateData);

        $response->assertOk();

        $media = $trip->fresh()->media->first();
        $this->assertNotNull($media, 'Media was not created on update');

        $this->assertEquals('New Awesome Photo', $media->title, 'Title match failed on update');
        $this->assertEquals('New Photographer', $media->photographer, 'Photographer match failed on update');
    }
}
