<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Public trips are readable without logging in, so the embedded caves must not leak
 * what CaveResource withholds from viewers without an approved club.
 */
class TripLocationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $system = CaveSystem::factory()->create(['references' => 'Survey ref']);
        $cave = Cave::factory()->create([
            'cave_system_id' => $system->id,
            'location_lat' => 54.1,
            'location_lng' => -2.4,
            'access_info' => 'Ask the farmer',
        ]);
        $this->trip = Trip::factory()->create([
            'visibility' => 'public',
            'cave_system_id' => $system->id,
            'entrance_cave_id' => $cave->id,
            'exit_cave_id' => $cave->id,
        ]);
    }

    public static function viewersWithoutClub(): array
    {
        return ['guest' => [false], 'user without approved club' => [true]];
    }

    #[Test]
    #[DataProvider('viewersWithoutClub')]
    public function a_public_trip_hides_cave_locations_from_viewers_without_an_approved_club(bool $loggedIn): void
    {
        if ($loggedIn) {
            $this->actingAs(User::factory()->create());
        }

        $data = $this->getJson('/api/trips/'.$this->trip->short_id)->assertOk()->json('data');

        foreach (['entrance', 'exit'] as $cave) {
            $this->assertNotNull($data[$cave]['name']);
            $this->assertNull($data[$cave]['location_lat']);
            $this->assertNull($data[$cave]['location_lng']);
            $this->assertNull($data[$cave]['access_info']);
        }
        $this->assertNull($data['system']['references']);
    }

    #[Test]
    public function approved_club_members_see_cave_locations_on_a_trip(): void
    {
        $this->actingAs(User::factory()->withApprovedClub()->create());

        $data = $this->getJson('/api/trips/'.$this->trip->short_id)->assertOk()->json('data');

        $this->assertEquals(54.1, $data['entrance']['location_lat']);
        $this->assertSame('Ask the farmer', $data['entrance']['access_info']);
        $this->assertSame('Survey ref', $data['system']['references']);
    }

    #[Test]
    public function the_trips_list_hides_entrance_locations_from_users_without_an_approved_club(): void
    {
        // /api/trips requires a login, so only the logged-in case applies.
        $this->actingAs(User::factory()->create());

        $trip = collect($this->getJson('/api/trips')->assertOk()->json('data'))->firstWhere('id', $this->trip->short_id);

        $this->assertNotNull($trip['entrance']['name']);
        $this->assertNull($trip['entrance']['location_lat']);
        $this->assertNull($trip['entrance']['location_lng']);
    }

    #[Test]
    public function the_trips_list_shows_entrance_locations_to_approved_club_members(): void
    {
        $this->actingAs(User::factory()->withApprovedClub()->create());

        $trip = collect($this->getJson('/api/trips')->assertOk()->json('data'))->firstWhere('id', $this->trip->short_id);

        $this->assertEquals(54.1, $trip['entrance']['location_lat']);
        $this->assertEquals(-2.4, $trip['entrance']['location_lng']);
    }
}
