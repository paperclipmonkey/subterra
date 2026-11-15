<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Route;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A cave system's 3D view (cave-viewer) on its caves' pages and route guides:
 * where the view opens for each cave, and a guide's way through it.
 */
class Cave3DViewTest extends TestCase
{
    use RefreshDatabase;

    private const VIEWER = 'https://cavesurveying.github.io/gb.mendips.burrington/goatchurch/3d/';

    private const LINK = '#from=Goatchurch%20Cavern&to=The%20Drainpipe';

    /** @return array<string, mixed> */
    private function viewerRoute(): array
    {
        return [
            'link' => self::LINK,
            'length' => 140,
            'up' => 4,
            'down' => 38,
            'another' => false,
            'stops' => [['name' => 'The Drainpipe', 'at' => 140, 'time' => '2026-01-01T10:40:00Z', 'extra' => 'x']],
            'passages' => ['Boulder Chamber', 'The Drainpipe'],
            'extra' => 'dropped',
        ];
    }

    #[Test]
    public function a_caves_page_has_its_systems_3d_view_and_where_it_opens(): void
    {
        $system = CaveSystem::factory()->create(['viewer_url' => self::VIEWER]);
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);
        $admin = User::factory()->dataAdmin()->create();

        $this->actingAs($admin)
            ->putJson('/api/caves/'.$cave->slug, ['viewer_at' => 'Goatchurch Cavern, second entrance'])
            ->assertOk();

        $this->getJson('/api/caves/'.$cave->slug)
            ->assertOk()
            ->assertJsonPath('data.viewer_at', 'Goatchurch Cavern, second entrance')
            ->assertJsonPath('data.system.viewer_url', self::VIEWER);
    }

    #[Test]
    public function a_route_guide_keeps_its_way_through_the_3d_view(): void
    {
        $admin = User::factory()->admin()->create();
        $system = CaveSystem::factory()->create(['viewer_url' => self::VIEWER]);

        $response = $this->actingAs($admin)->postJson("/api/cave_systems/{$system->id}/routes", [
            'name' => 'To the Drainpipe',
            'viewer_route' => $this->viewerRoute(),
        ])->assertCreated();

        $route = Route::findOrFail($response->json('id'));
        $this->assertSame(self::LINK, $route->viewer_route['link']);
        $this->assertArrayNotHasKey('extra', $route->viewer_route);
        // a guide's stops have no times: those are a trip's
        $this->assertSame([['name' => 'The Drainpipe', 'at' => 140]], $route->viewer_route['stops']);

        $this->getJson("/api/routes/{$route->slug}")->assertOk()
            ->assertJsonPath('viewer_route.length', 140)
            ->assertJsonPath('cave_system.viewer_url', self::VIEWER);
    }

    #[Test]
    public function a_route_guides_way_comes_as_json_text_with_images_and_can_be_taken_off(): void
    {
        $admin = User::factory()->admin()->create();
        $route = Route::factory()->create();

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->put("/api/routes/{$route->slug}", ['viewer_route' => json_encode($this->viewerRoute())])
            ->assertOk();
        $this->assertSame(self::LINK, $route->fresh()->viewer_route['link']);

        $this->putJson("/api/routes/{$route->slug}", ['viewer_route' => ''])->assertOk();
        $this->assertNull($route->fresh()->viewer_route);
    }

    #[Test]
    public function a_route_guides_way_must_be_a_cave_viewer_link(): void
    {
        $admin = User::factory()->admin()->create();
        $route = Route::factory()->create();

        $this->actingAs($admin)
            ->putJson("/api/routes/{$route->slug}", ['viewer_route' => ['link' => 'javascript:alert(1)']])
            ->assertStatus(422);
    }
}
