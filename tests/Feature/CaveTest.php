<?php

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $cave = Cave::factory()->create();

        $base64Image = 'data:image/png;base64,'.base64_encode(file_get_contents(__DIR__.'/../../Fixtures/test.png'));

        $data = [
            'hero_image' => [
                'data' => $base64Image,
                'title' => 'Hero Title',
                'photographer' => 'Hero Photog',
            ],
            'entrance_image' => [
                'data' => $base64Image,
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
}
