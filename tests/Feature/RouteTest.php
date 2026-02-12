<?php

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

        // Mock a base64 image
        $image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $data = [
            'name' => 'Hero Route',
            'hero_image' => $image,
        ];

        $response = $this->actingAs($admin)->postJson("/api/cave_systems/{$system->id}/routes", $data);

        $response->assertStatus(201);
        $route = Route::where('name', 'Hero Route')->first();
        $this->assertNotNull($route->hero_image);
        // Ensure it's not the raw base64 string
        $this->assertStringNotContainsString('data:image', $route->hero_image);
    }

    public function test_admin_can_upload_media_for_route()
    {
        $admin = User::factory()->admin()->create();
        $system = CaveSystem::factory()->create();

        $image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $pdf = 'data:application/pdf;base64,JVBERi0xLg0KMSAwIG9iago8PC9UeXBlL0NhdGFsb2cvUGFnZXMgMiAwIFI+Pg0KZW5kb2JqAyAwIG9iago8PC9UeXBlL1BhZ2VzL0t'; // Truncated valid-ish header

        $data = [
            'name' => 'Media Route',
            'media' => [
                ['data' => $image, 'caption' => 'A photo', 'type' => 'photo'],
                ['data' => $pdf, 'caption' => 'A survey', 'type' => 'pdf'],
            ],
        ];

        $response = $this->actingAs($admin)->postJson("/api/cave_systems/{$system->id}/routes", $data);

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
}
