<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Cave;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class CaveTest extends TestCase
{
    use RefreshDatabase;
    /** @test */
    public function it_can_get_the_list_of_caves()
    {
        Cave::factory()->count(3)->create();

        $response = $this->get('/api/caves');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_get_a_single_cave_by_slug()
    {
        $cave = Cave::factory()->create([
            'slug' => 'test-cave'
        ]);

        $response = $this->get('/api/caves/' . $cave->slug);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_updates_a_cave_and_syncs_tags() {
        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true]));
        $cave = Cave::factory()->create();
        $tag = Tag::factory()->create(['category' => 'test', 'tag' => 'tag', 'assignable' => true]);

        $data = [
            'name' => 'Updated Cave',
            'tags' => [
                [
                    'category' => $tag->category,
                    'tag' => $tag->tag,
                ]
            ]
        ];

        $response = $this->putJson('/api/caves/' . $cave->slug, $data);

        $response->assertOk();
        $this->assertDatabaseHas('caves', ['id' => $cave->id, 'name' => 'Updated Cave']);
        $this->assertTrue($cave->fresh()->tags->contains($tag));
    }

    /** @test */
    public function it_updates_hero_and_entrance_images()
    {
        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true]));
        Storage::fake('media');
        $cave = Cave::factory()->create();

        $base64Image = 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../../Fixtures/test.png'));

        $data = [
            'hero_image' => [
                'data' => $base64Image,
            ],
            'entrance_image' => [
                'data' => $base64Image,
            ],
        ];

        $response = $this->putJson('/api/caves/' . $cave->slug, $data);

        $response->assertOk();
        $cave = $cave->fresh();
        Storage::disk('media')->assertExists($cave->hero_image);
        Storage::disk('media')->assertExists($cave->entrance_image);
    }

    /** @test */
    public function it_removes_images_when_null_is_passed()
    {
        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true]));
        Storage::fake('media');
        $cave = Cave::factory()->create([
            'hero_image' => 'caves/old_hero.webp',
            'entrance_image' => 'caves/old_entrance.webp',
        ]);
        Storage::disk('media')->put('caves/old_hero.webp', 'dummy');
        Storage::disk('media')->put('caves/old_entrance.webp', 'dummy');

        $data = [
            'hero_image' => null,
            'entrance_image' => null,
        ];

        $response = $this->putJson('/api/caves/' . $cave->slug, $data);

        $response->assertOk();
        $cave = $cave->fresh();
        $this->assertNull($cave->hero_image);
        $this->assertNull($cave->entrance_image);
    }
}