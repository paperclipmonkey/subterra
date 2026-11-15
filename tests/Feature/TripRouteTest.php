<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\TripRouteTimesController;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A trip's route through its cave's 3D survey (cave-viewer), and the times
 * other parties took over the same route.
 */
class TripRouteTest extends TestCase
{
    use RefreshDatabase;

    private const LINK = '#from=Goatchurch%20Cavern&to=Goatchurch%20Cavern&via=Station%20(stn%20goatchurch.25)&another=1';

    private function route(array $extra = []): array
    {
        return [
            'link' => self::LINK,
            'length' => 293,
            'up' => 51,
            'down' => 51,
            'another' => true,
            'stops' => [
                ['name' => 'Station (stn goatchurch.25)', 'at' => 38, 'time' => '2026-01-01T10:40:00Z'],
                ['name' => 'Goatchurch Cavern', 'at' => 293],
            ],
            'passages' => ['Boulder Chamber', 'The Drainpipe'],
        ] + $extra;
    }

    private function tripData(Cave $entrance, User $participant, mixed $route): array
    {
        return [
            'name' => 'Round the loop',
            'start_time' => '2026-01-01 10:00:00',
            'end_time' => '2026-01-01 12:00:00',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'participants' => [$participant->id],
            'route' => $route,
        ];
    }

    #[Test]
    public function a_trip_keeps_its_route(): void
    {
        Event::fake([\App\Events\TripCreated::class]);
        $user = User::factory()->create();
        $entrance = Cave::factory()->create();

        $this->actingAs($user);
        $response = $this->postJson('/api/trips', $this->tripData($entrance, $user, $this->route(['extra' => 'dropped'])));

        $response->assertCreated();
        $this->assertSame(self::LINK, $response->json('data.route.link'));
        $this->assertSame(38, $response->json('data.route.stops.0.at'));
        $this->assertArrayNotHasKey('extra', $response->json('data.route'));
        $this->assertSame(293, Trip::first()->route['length']);
    }

    #[Test]
    public function a_route_sent_with_photos_comes_as_json_text(): void
    {
        Event::fake([\App\Events\TripCreated::class]);
        $user = User::factory()->create();
        $entrance = Cave::factory()->create();

        $this->actingAs($user);
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/trips', $this->tripData($entrance, $user, json_encode($this->route())));

        $response->assertCreated();
        $this->assertSame(self::LINK, Trip::first()->route['link']);
    }

    #[Test]
    public function a_route_must_be_a_cave_viewer_link(): void
    {
        $user = User::factory()->create();
        $entrance = Cave::factory()->create();

        $this->actingAs($user);
        $this->postJson('/api/trips', $this->tripData($entrance, $user, ['link' => 'javascript:alert(1)']))
            ->assertStatus(422);
    }

    #[Test]
    public function a_route_can_be_added_later_and_taken_off(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'public']);
        $trip->participants()->attach($user);

        $this->actingAs($user);
        $this->putJson("/api/trips/{$trip->short_id}", ['route' => $this->route()])->assertOk();
        $this->assertSame(self::LINK, $trip->fresh()->route['link']);

        $this->putJson("/api/trips/{$trip->short_id}", ['route' => null])->assertOk();
        $this->assertNull($trip->fresh()->route);
    }

    #[Test]
    public function a_cave_system_names_its_3d_view(): void
    {
        $admin = User::factory()->admin()->create();
        $system = CaveSystem::factory()->create();

        $this->actingAs($admin);
        $this->putJson("/api/cave_systems/{$system->id}", ['viewer_url' => 'https://cavesurveying.github.io/gb.mendips.burrington/goatchurch/3d/'])
            ->assertOk();
        $this->assertSame('https://cavesurveying.github.io/gb.mendips.burrington/goatchurch/3d/', $system->fresh()->viewer_url);

        $this->putJson("/api/cave_systems/{$system->id}", ['viewer_url' => 'http://example.com/'])->assertStatus(422);
    }

    #[Test]
    public function the_same_route_matches_however_its_link_is_written(): void
    {
        $a = TripRouteTimesController::routeKey('#from=A&to=B&via=C%7CD&step=3&plan=1');
        $b = TripRouteTimesController::routeKey('#via=C|D&to=B&from=A');
        $this->assertSame($a, $b);
        $this->assertNotSame($a, TripRouteTimesController::routeKey('#from=A&to=B&via=C|D&another=1'));
    }

    #[Test]
    public function route_times_need_three_public_trips(): void
    {
        $system = CaveSystem::factory()->create();
        $make = fn (int $mins, string $visibility = 'public', ?string $stopAt = null) => Trip::factory()->create([
            'cave_system_id' => $system->id,
            'visibility' => $visibility,
            'start_time' => '2026-01-01 10:00:00',
            'end_time' => now()->parse('2026-01-01 10:00:00')->addMinutes($mins),
            'route' => ['link' => self::LINK.'&step=2', 'stops' => [['name' => 'x', 'time' => $stopAt]]],
        ]);
        $make(100, 'public', '2026-01-01 10:30:00');
        $make(120, 'public', '2026-01-01 10:40:00');
        $make(500, 'private', '2026-01-01 10:50:00');   // not counted

        $url = "/api/cave_systems/{$system->id}/route-times?link=".rawurlencode(self::LINK);
        $this->getJson($url)->assertOk()->assertJson(['trips' => null, 'duration' => null, 'stops' => []]);

        $make(140, 'public', '2026-01-01 10:50:00');
        $this->getJson($url)->assertOk()->assertJson([
            'trips' => 3,
            'duration' => ['median' => 120, 'quick' => 120, 'slow' => 140],
            'stops' => [['stop' => 0, 'median' => 40]],
        ]);
    }
}
