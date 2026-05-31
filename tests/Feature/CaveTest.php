<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JsonSchemaValidator;
use Tests\TestCase;

class CaveTest extends TestCase
{
    use RefreshDatabase;
    use JsonSchemaValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TagSeeder::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_get_the_list_of_caves()
    {
        $this->actingAs(User::factory()->withApprovedClub()->create());
        Cave::factory()->count(3)->create();

        $response = $this->get('/api/caves');

        $response->assertStatus(200);
        $this->assertResponseMatchesSchema($response, 'endpoints/caves-index');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_get_a_single_cave_by_slug()
    {
        $this->actingAs(User::factory()->withApprovedClub()->create());

        $cave = Cave::factory()->create([
            'slug' => 'test-cave',
        ]);

        $response = $this->get('/api/caves/'.$cave->slug);

        $response->assertStatus(200);
        $this->assertResponseMatchesSchema($response, 'endpoints/caves-show');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_view_a_cave_by_slug_with_non_numeric_slug()
    {
        $this->actingAs(User::factory()->withApprovedClub()->create());

        $cave = Cave::factory()->create([
            'slug' => 'qui-a-repellat-numquam',
        ]);

        // This triggers the TrackApiInteraction middleware
        $response = $this->getJson("/api/caves/{$cave->slug}");

        $response->assertOk();
        $response->assertJsonFragment(['slug' => 'qui-a-repellat-numquam']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_a_cave_and_syncs_tags()
    {
        $this->actingAs(\App\Models\User::factory()->dataAdmin()->create());
        $cave = Cave::factory()->create();
        $tag = Tag::factory()->create(['category' => 'test', 'tag' => 'tag', 'assignable' => true]);

        $data = [
            'name' => 'Updated Cave',
            'tags' => [
                [
                    'category' => $tag->category,
                    'tag' => $tag->tag,
                ],
            ],
        ];

        $response = $this->putJson('/api/caves/'.$cave->slug, $data);

        $response->assertOk();
        $this->assertDatabaseHas('caves', ['id' => $cave->id, 'name' => 'Updated Cave']);
        $this->assertTrue($cave->fresh()->tags->contains($tag));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_hero_and_entrance_images()
    {
        $this->actingAs(\App\Models\User::factory()->dataAdmin()->create());
        Storage::fake('media');
        $imageFile = UploadedFile::fake()->image('test.png');
        $cave = Cave::factory()->create();

        $data = [
            'hero_image' => [
                'data' => $imageFile,
                'title' => 'Hero Title',
                'photographer' => 'Hero Photog',
            ],
            'entrance_image' => [
                'data' => $imageFile,
                'title' => 'Entrance Title',
            ],
        ];

        $response = $this->putJson('/api/caves/'.$cave->slug, $data);

        $response->assertOk();
        $cave->refresh();
        $this->assertNotNull($cave->heroImage);
        $this->assertNotNull($cave->entranceImage);
        Storage::disk('media')->assertExists($cave->heroImage->filename);
        Storage::disk('media')->assertExists($cave->entranceImage->filename);
        $this->assertEquals('Hero Title', $cave->heroImage->title);
        $this->assertEquals('Hero Photog', $cave->heroImage->photographer);
        $this->assertEquals('Entrance Title', $cave->entranceImage->title);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_removes_images_when_null_is_passed()
    {
        $this->actingAs(\App\Models\User::factory()->dataAdmin()->create());
        Storage::fake('media');
        $cave = Cave::factory()->create();

        $cave->media()->create(['type' => 'hero', 'filename' => 'caves/old_hero.webp']);
        $cave->media()->create(['type' => 'entrance', 'filename' => 'caves/old_entrance.webp']);

        Storage::disk('media')->put('caves/old_hero.webp', 'dummy');
        Storage::disk('media')->put('caves/old_entrance.webp', 'dummy');

        $data = [
            'hero_image' => null,
            'entrance_image' => null,
        ];

        $response = $this->putJson('/api/caves/'.$cave->slug, $data);

        $response->assertOk();
        $cave->refresh();
        $this->assertNull($cave->heroImage);
        $this->assertNull($cave->entranceImage);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_thumbnail_url_in_cave_details()
    {
        $this->actingAs(\App\Models\User::factory()->withApprovedClub()->create());
        $caveSystem = \App\Models\CaveSystem::factory()->create();
        $file = $caveSystem->files()->create([
            'filename' => 'test.jpg',
            'original_filename' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'thumbnail_filename' => 'thumb.webp',
        ]);
        $cave = Cave::factory()->create(['cave_system_id' => $caveSystem->id]);

        $response = $this->getJson("/api/caves/{$cave->slug}");

        $response->assertOk()
            ->assertJsonPath('data.system.files.0.thumbnail_url', function ($url) {
                return str_contains($url, 'thumb.webp');
            });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function private_trips_are_not_visible_to_non_participants_in_cave_show()
    {
        $cave = Cave::factory()->create();
        $participant = User::factory()->create();
        $nonParticipant = User::factory()->create();

        // Create a private trip for the cave
        $privateTrip = \App\Models\Trip::factory()->create([
            'entrance_cave_id' => $cave->id,
            'cave_system_id' => $cave->cave_system_id,
            'visibility' => 'private',
        ]);
        $privateTrip->participants()->attach($participant->id);

        // Sanity check: Public trip should be visible
        $publicTrip = \App\Models\Trip::factory()->create([
            'entrance_cave_id' => $cave->id,
            'cave_system_id' => $cave->cave_system_id,
            'visibility' => 'public',
        ]);

        $this->actingAs($nonParticipant, 'sanctum');

        $response = $this->getJson("/api/caves/{$cave->slug}");

        $response->assertOk();

        // Assert public trip is present
        $response->assertJsonFragment(['id' => $publicTrip->short_id]);

        // Assert private trip is MISSING
        $response->assertJsonMissing(['id' => $privateTrip->short_id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function private_trips_are_visible_to_participants_in_cave_show()
    {
        $cave = Cave::factory()->create();
        $participant = User::factory()->create();

        $privateTrip = \App\Models\Trip::factory()->create([
            'entrance_cave_id' => $cave->id,
            'cave_system_id' => $cave->cave_system_id,
            'visibility' => 'private',
        ]);
        $privateTrip->participants()->attach($participant->id);

        $this->actingAs($participant, 'sanctum');

        $response = $this->getJson("/api/caves/{$cave->slug}");

        $response->assertOk();
        $response->assertJsonFragment(['id' => $privateTrip->short_id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_only_curated_caves_when_curated_filter_is_applied()
    {
        $this->actingAs(User::factory()->withApprovedClub()->create());

        $curatedTag = Tag::where('tag', 'Curated')->where('category', 'curated')->firstOrFail();

        $curated = Cave::factory()->create(['name' => 'Curated Cave']);
        $curated->tags()->attach($curatedTag->id);

        Cave::factory()->create(['name' => 'Non Curated Cave']);

        $response = $this->getJson('/api/caves?curated=1');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Curated Cave', $data[0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_all_caves_when_curated_filter_is_not_applied()
    {
        $this->actingAs(User::factory()->withApprovedClub()->create());

        $curatedTag = Tag::where('tag', 'Curated')->where('category', 'curated')->firstOrFail();

        $curated = Cave::factory()->create(['name' => 'Curated Cave']);
        $curated->tags()->attach($curatedTag->id);

        Cave::factory()->create(['name' => 'Non Curated Cave']);

        $response = $this->getJson('/api/caves');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_array_when_curated_filter_applied_but_no_curated_caves_exist()
    {
        $this->actingAs(User::factory()->withApprovedClub()->create());

        Cave::factory()->count(3)->create();

        $response = $this->getJson('/api/caves?curated=1');

        $response->assertOk()
            ->assertJson(['data' => []]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function curated_filter_response_matches_schema()
    {
        $this->actingAs(User::factory()->withApprovedClub()->create());

        $curatedTag = Tag::where('tag', 'Curated')->where('category', 'curated')->firstOrFail();

        $cave = Cave::factory()->create();
        $cave->tags()->attach($curatedTag->id);

        $response = $this->getJson('/api/caves?curated=1');

        $response->assertOk();
        $this->assertResponseMatchesSchema($response, 'endpoints/caves-index');
    }
}
