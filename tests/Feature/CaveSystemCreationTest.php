<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaveSystem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaveSystemCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->admin()->create();
        $this->actingAs($this->user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_saves_hero_image_with_metadata_when_using_json_payload()
    {
        Storage::fake('media');

        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('test.png');

        $payload = [
            'system' => [
                'name' => 'New System',
                'slug' => 'new-system',
                'length' => 1000,
                'vertical_range' => 100,
                'description' => 'System description',
            ],
            'cave' => [
                'name' => 'New Cave',
                'slug' => 'new-cave',
                'location_name' => 'Location',
                'location_country' => 'Country',
                'location_lat' => 50.0,
                'location_lng' => -1.5,
                'hero_image' => [
                    'data' => $imageFile,
                    'title' => 'Hero Title',
                    'photographer' => 'Photo Grapher',
                    'copyright' => 'Copy Right',
                ],
            ],
        ];

        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/cave_systems_with_cave', $payload);

        $response->assertCreated();

        $caveSystem = CaveSystem::where('name', 'New System')->first();
        $this->assertNotNull($caveSystem);

        $cave = $caveSystem->caves()->first();
        $this->assertNotNull($cave);

        $this->assertDatabaseHas('cave_media', [
            'cave_id' => $cave->id,
            'type' => 'hero',
            'title' => 'Hero Title',
            'photographer' => 'Photo Grapher',
            'copyright' => 'Copy Right',
        ]);
    }
}
