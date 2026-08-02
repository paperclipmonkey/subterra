<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Route;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_routes_for_cave_system()
    {
        $system = CaveSystem::factory()->create();
        Route::factory(3)->for($system)->create();

        $user = User::factory()->create();
        $response = $this->actingAs($user)->get("/api/cave_systems/{$system->id}/routes");

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_can_show_route_details()
    {
        $route = Route::factory()->create();

        $user = User::factory()->create();
        $response = $this->actingAs($user)->get("/api/routes/{$route->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $route->id,
                'name' => $route->name,
            ]);
    }

    public function test_admin_can_create_route()
    {
        $admin = User::factory()->admin()->create();
        $system = CaveSystem::factory()->create();
        $entrance = Cave::factory()->create(['cave_system_id' => $system->id]);

        $data = [
            'name' => 'Test Route',
            'description' => 'A test route description',
            'entrance_id' => $entrance->id,
            'grade' => 3,
            'duration' => '2 hours',
            'tackle' => [
                [
                    'description' => 'Main pitch',
                    'type' => 'rope',
                    'length' => 30,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->postJson("/api/cave_systems/{$system->id}/routes", $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('routes', ['name' => 'Test Route']);
        $this->assertDatabaseHas('route_tackles', ['description' => 'Main pitch']);
    }

    public function test_admin_can_update_route()
    {
        $admin = User::factory()->admin()->create();
        $route = Route::factory()->create();

        $data = [
            'name' => 'Updated Route Name',
            'grade' => 4,
        ];

        $response = $this->actingAs($admin)->putJson("/api/routes/{$route->id}", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('routes', ['id' => $route->id, 'name' => 'Updated Route Name']);
    }

    public function test_admin_can_replace_tackle_on_update()
    {
        $admin = User::factory()->admin()->create();
        $route = Route::factory()->create();
        $route->tackle()->create(['description' => 'Old pitch', 'type' => 'rope', 'length' => 15, 'quantity' => 1]);

        $response = $this->actingAs($admin)->putJson("/api/routes/{$route->id}", [
            'tackle' => [
                ['description' => 'New pitch', 'type' => 'srt_rope', 'length' => 25, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('route_tackles', ['route_id' => $route->id, 'description' => 'New pitch']);
        $this->assertDatabaseMissing('route_tackles', ['route_id' => $route->id, 'description' => 'Old pitch']);
    }

    public function test_update_rejects_tackle_items_missing_required_fields()
    {
        $admin = User::factory()->admin()->create();
        $route = Route::factory()->create();
        $route->tackle()->create(['description' => 'Existing pitch', 'type' => 'rope', 'length' => 15, 'quantity' => 1]);

        $response = $this->actingAs($admin)->putJson("/api/routes/{$route->id}", [
            'tackle' => [
                ['length' => 20], // no description or type — must 422, not 500
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tackle.0.description', 'tackle.0.type']);

        // The existing tackle must be untouched by the failed update
        $this->assertDatabaseHas('route_tackles', ['route_id' => $route->id, 'description' => 'Existing pitch']);
    }

    public function test_admin_can_delete_route()
    {
        $admin = User::factory()->admin()->create();
        $route = Route::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/routes/{$route->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('routes', ['id' => $route->id]);
    }

    public function test_non_admin_cannot_create_route()
    {
        $user = User::factory()->create();
        $system = CaveSystem::factory()->create();

        $data = ['name' => 'Test Route'];

        $response = $this->actingAs($user)->postJson("/api/cave_systems/{$system->id}/routes", $data);

        $response->assertStatus(403);
    }

    public function test_admin_can_create_route_with_specific_tackle_types()
    {
        $admin = User::factory()->admin()->create();
        $system = CaveSystem::factory()->create();
        $entrance = Cave::factory()->create(['cave_system_id' => $system->id]);

        $data = [
            'name' => 'Tackle Route',
            'entrance_id' => $entrance->id,
            'tackle' => [
                ['type' => 'srt_rope', 'description' => 'First Pitch', 'length' => 20, 'quantity' => 1],
                ['type' => 'handline', 'description' => 'Climb', 'length' => 10, 'quantity' => 1, 'optional' => true],
                ['type' => 'ladder', 'description' => 'Pitch', 'length' => 10, 'quantity' => 1],
                ['type' => 'rope_protector', 'description' => 'Rub point', 'quantity' => 2],
            ],
        ];

        $response = $this->actingAs($admin)->postJson("/api/cave_systems/{$system->id}/routes", $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('route_tackles', ['type' => 'srt_rope', 'description' => 'First Pitch']);
        $this->assertDatabaseHas('route_tackles', ['type' => 'handline', 'optional' => 1]);
        $this->assertDatabaseHas('route_tackles', ['type' => 'ladder']);
        $this->assertDatabaseHas('route_tackles', ['type' => 'rope_protector', 'quantity' => 2]);
    }

    public function test_admin_can_upload_hero_image_for_route()
    {
        $admin = User::factory()->admin()->create();
        $system = CaveSystem::factory()->create();

        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('test.png');

        $data = [
            'name' => 'Hero Route',
            'hero_image' => [
                'data' => $imageFile,
                'photographer' => 'Jane Doe',
                'copyright' => '© Jane Doe',
            ],
        ];

        $response = $this->actingAs($admin)->withHeaders(['Accept' => 'application/json'])->post("/api/cave_systems/{$system->id}/routes", $data);

        $response->assertStatus(201);
        $route = Route::where('name', 'Hero Route')->first();

        // A real file path is stored, not the raw base64 string.
        $this->assertNotNull($route->getRawOriginal('hero_image'));
        $this->assertStringNotContainsString('data:image', $route->getRawOriginal('hero_image'));
        $this->assertSame('Jane Doe', $route->hero_image_photographer);
        $this->assertSame('© Jane Doe', $route->hero_image_copyright);

        // The accessor exposes a nested object with credits.
        $this->assertIsArray($route->hero_image);
        $this->assertArrayHasKey('url', $route->hero_image);
        $this->assertSame('Jane Doe', $route->hero_image['photographer']);
        $this->assertSame('© Jane Doe', $route->hero_image['copyright']);
    }

    public function test_admin_can_update_hero_image_via_multipart_method_spoofing()
    {
        // Mirrors the real frontend request: a multipart POST with _method=PUT
        // carrying the hero image as a genuine uploaded file.
        $admin = User::factory()->admin()->create();
        $route = Route::factory()->create(['hero_image' => 'routes/old.jpg']);

        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('new-hero.png');

        $response = $this->actingAs($admin)->withHeaders(['Accept' => 'application/json'])
            ->post("/api/routes/{$route->slug}", [
                '_method' => 'PUT',
                'name' => 'Updated Hero Route',
                'hero_image' => [
                    'data' => $imageFile,
                    'photographer' => 'New Photographer',
                    'copyright' => 'New Copyright',
                ],
            ]);

        $response->assertStatus(200);
        $route->refresh();
        $this->assertSame('Updated Hero Route', $route->name);
        $this->assertNotNull($route->getRawOriginal('hero_image'));
        $this->assertNotSame('routes/old.jpg', $route->getRawOriginal('hero_image'));
        $this->assertStringNotContainsString('data:image', $route->getRawOriginal('hero_image'));
        $this->assertSame('New Photographer', $route->hero_image_photographer);
        $this->assertSame('New Copyright', $route->hero_image_copyright);
    }

    public function test_admin_can_update_hero_image_credits_without_replacing_the_file()
    {
        $admin = User::factory()->admin()->create();
        $route = Route::factory()->create([
            'hero_image' => 'routes/existing.jpg',
            'hero_image_photographer' => 'Old Photographer',
            'hero_image_copyright' => 'Old Copyright',
        ]);

        $response = $this->actingAs($admin)->withHeaders(['Accept' => 'application/json'])
            ->post("/api/routes/{$route->slug}", [
                '_method' => 'PUT',
                'name' => $route->name,
                'hero_image' => [
                    'photographer' => 'Updated Photographer',
                    'copyright' => 'Updated Copyright',
                ],
            ]);

        $response->assertStatus(200);
        $route->refresh();
        // Image file is preserved when no new file is sent.
        $this->assertSame('routes/existing.jpg', $route->getRawOriginal('hero_image'));
        $this->assertSame('Updated Photographer', $route->hero_image_photographer);
        $this->assertSame('Updated Copyright', $route->hero_image_copyright);
    }

    public function test_admin_can_upload_media_for_route()
    {
        $admin = User::factory()->admin()->create();
        $system = CaveSystem::factory()->create();

        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('test.png');
        $pdfFile = \Illuminate\Http\UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $data = [
            'name' => 'Media Route',
            'media' => [
                ['data' => $imageFile, 'caption' => 'A photo', 'type' => 'photo'],
                ['data' => $pdfFile, 'caption' => 'A survey', 'type' => 'pdf'],
            ],
        ];

        $response = $this->actingAs($admin)->withHeaders(['Accept' => 'application/json'])->post("/api/cave_systems/{$system->id}/routes", $data);

        $response->assertStatus(201);
        $route = Route::where('name', 'Media Route')->first();
        $this->assertCount(2, $route->media);
        $this->assertDatabaseHas('route_media', ['route_id' => $route->id, 'caption' => 'A photo', 'type' => 'photo']);
        $this->assertDatabaseHas('route_media', ['route_id' => $route->id, 'caption' => 'A survey', 'type' => 'pdf']);
    }

    public function test_admin_can_delete_media_from_route()
    {
        $admin = User::factory()->admin()->create();
        $route = Route::factory()->create();

        // Create initial media
        $media1 = $route->media()->create(['path' => 'path/to/img1.jpg', 'type' => 'photo']);
        $media2 = $route->media()->create(['path' => 'path/to/img2.jpg', 'type' => 'photo']);

        $data = [
            'deleted_media' => [$media1->id],
        ];

        $response = $this->actingAs($admin)->putJson("/api/routes/{$route->id}", $data);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('route_media', ['id' => $media1->id]);
        $this->assertDatabaseHas('route_media', ['id' => $media2->id]);
    }

    public function test_factory_assigns_entrance_and_exit_within_the_routes_system()
    {
        // Default factory: entrance/exit belong to the route's own system.
        $route = Route::factory()->create();
        $this->assertSame($route->cave_system_id, $route->entrance->cave_system_id);
        $this->assertSame($route->cave_system_id, $route->exit->cave_system_id);

        // When the system is supplied via for(), entrance/exit reuse its caves.
        $system = CaveSystem::factory()->create();
        Cave::factory(3)->create(['cave_system_id' => $system->id]);
        $forRoute = Route::factory()->for($system)->create();

        $this->assertSame($system->id, $forRoute->cave_system_id);
        $this->assertSame($system->id, $forRoute->entrance->cave_system_id);
        $this->assertSame($system->id, $forRoute->exit->cave_system_id);
    }

    public function test_can_resolve_route_by_slug()
    {
        $route = Route::factory()->create(['name' => 'Slug Route']);
        // Ensure slug is generated/set (factory might not do it depending on definition, but Route::create in controller does)
        // Let's manually set it to be sure for this test
        $route->slug = 'slug-route';
        $route->save();

        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/api/routes/slug-route');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $route->id,
                'name' => 'Slug Route',
            ]);
    }

    public function test_it_returns_404_for_non_numeric_non_existent_slug_without_sql_error()
    {
        $user = User::factory()->create();

        // This would previously cause an SQL error "invalid input syntax for type bigint"
        $response = $this->actingAs($user)->get('/api/routes/non-existent-string-slug');

        $response->assertStatus(404);
    }
}
