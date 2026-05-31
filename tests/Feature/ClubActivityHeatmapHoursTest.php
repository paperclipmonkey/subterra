<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubActivityHeatmapHoursTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;
    private User $member1;
    private User $member2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->member1 = User::factory()->create();
        $this->member2 = User::factory()->create();

        $this->club->users()->attach($this->member1, ['status' => 'approved']);
        $this->club->users()->attach($this->member2, ['status' => 'approved']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function heatmap_shows_cumulative_hours_underground_per_day()
    {
        // Trip 1: 2 hours, 2 participants -> 4 hours total
        $trip1 = Trip::factory()->create([
            'start_time' => Carbon::parse('2023-01-01 10:00:00'),
            'end_time' => Carbon::parse('2023-01-01 12:00:00'),
        ]);
        $trip1->participants()->attach([$this->member1->id, $this->member2->id]);

        // Trip 2: 1 hour, 1 participant -> 1 hour total
        $trip2 = Trip::factory()->create([
            'start_time' => Carbon::parse('2023-01-01 14:00:00'),
            'end_time' => Carbon::parse('2023-01-01 15:00:00'),
        ]);
        $trip2->participants()->attach([$this->member1->id]);

        // Trip 3: Yesterday, 1 hour, 2 participants -> 2 hours total
        $trip3 = Trip::factory()->create([
            'start_time' => Carbon::parse('2022-12-31 10:00:00'),
            'end_time' => Carbon::parse('2022-12-31 11:00:00'),
        ]);
        $trip3->participants()->attach([$this->member1->id, $this->member2->id]);

        // Simulate request hitting the API
        // We need to mock time or ensure the controller uses a date range that covers these dates.
        // The controller uses Carbon::now()->subYear().
        // So let's make sure these dates are within the last year relative to "now".
        // Instead of hardcoding 2023, let's use Carbon::now().

        Carbon::setTestNow(Carbon::parse('2023-06-01 12:00:00'));

        $this->actingAs($this->member1, 'sanctum');

        $response = $this->getJson("/api/clubs/{$this->club->slug}/activity-heatmap");

        $response->assertOk();

        // Expected data:
        // 2023-01-01: 5 hours (4 + 1)
        // 2022-12-31: 2 hours

        $response->assertJsonFragment(['date' => '2023-01-01', 'count' => 5]);
        $response->assertJsonFragment(['date' => '2022-12-31', 'count' => 2]);
    }
}
