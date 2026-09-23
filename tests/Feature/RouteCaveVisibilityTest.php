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
 * The route endpoints are public, so the entrance/exit caves they embed must
 * follow CaveResource's gates.
 */
class RouteCaveVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Route $route;

    private CaveSystem $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->system = CaveSystem::factory()->create();
        $entrance = Cave::factory()->create([
            'cave_system_id' => $this->system->id,
            'location_lat' => 54.1,
            'location_lng' => -2.4,
            'access_info' => 'Ask the farmer',
        ]);
        $mine = Cave::factory()->create(['cave_system_id' => $this->system->id, 'visibility' => 'admin_only']);
        $this->route = Route::factory()->for($this->system)->create(['entrance_id' => $entrance->id, 'exit_id' => $mine->id]);
    }

    private function fetchBoth(): array
    {
        return [
            $this->getJson("/api/routes/{$this->route->slug}")->assertOk()->json(),
            collect($this->getJson("/api/cave_systems/{$this->system->id}/routes")->assertOk()->json())->firstWhere('id', $this->route->id),
        ];
    }

    #[Test]
    public function guests_get_no_cave_locations_and_no_hidden_sites(): void
    {
        foreach ($this->fetchBoth() as $route) {
            $this->assertArrayNotHasKey('location_lat', $route['entrance']);
            $this->assertArrayNotHasKey('access_info', $route['entrance']);
            $this->assertNotNull($route['entrance']['name']);
            $this->assertNull($route['exit']);
        }
    }

    #[Test]
    public function approved_club_members_get_locations_but_not_hidden_sites(): void
    {
        $this->actingAs(User::factory()->withApprovedClub()->create());

        foreach ($this->fetchBoth() as $route) {
            $this->assertEquals(54.1, $route['entrance']['location_lat']);
            $this->assertNull($route['exit']);
        }
    }

    #[Test]
    public function data_admins_see_everything(): void
    {
        $this->actingAs(User::factory()->dataAdmin()->create());

        foreach ($this->fetchBoth() as $route) {
            $this->assertNotNull($route['exit']);
            $this->assertSame('Ask the farmer', $route['entrance']['access_info']);
        }
    }
}
