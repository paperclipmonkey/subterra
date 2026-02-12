<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserActivityHeatmapTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    private function getEndpointUrl(User $targetUser): string
    {
        return "/api/users/{$targetUser->id}/activity-heatmap";
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_activity_heatmap_data_in_hours(): void
    {
        // 1. Trip of 2 hours today
        Trip::factory()->create([
            'start_time' => Carbon::today()->setHour(10),
            'end_time' => Carbon::today()->setHour(12),
        ])->participants()->attach($this->user->id);

        // 2. Trip of 3.5 hours today (Total 5.5 hours)
        Trip::factory()->create([
            'start_time' => Carbon::today()->setHour(14),
            'end_time' => Carbon::today()->setHour(17)->addMinutes(30),
        ])->participants()->attach($this->user->id);

        // 3. Trip of 1.5 hours yesterday
        Trip::factory()->create([
            'start_time' => Carbon::yesterday()->setHour(10),
            'end_time' => Carbon::yesterday()->setHour(11)->addMinutes(30),
        ])->participants()->attach($this->user->id);

        // 4. Trip > 1 year ago (Should not be included)
        Trip::factory()->create([
            'start_time' => Carbon::now()->subYear()->subDay(),
            'end_time' => Carbon::now()->subYear()->subDay()->addHours(2),
        ])->participants()->attach($this->user->id);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson($this->getEndpointUrl($this->user));

        $response->assertOk();
        $response->assertJsonStructure([
            '*' => ['date', 'count'],
        ]);

        // Today: 5.5 hours
        $response->assertJsonFragment([
            'date' => Carbon::today()->toDateString(),
            'count' => 5.5,
        ]);

        // Yesterday: 1.5 hours
        $response->assertJsonFragment([
            'date' => Carbon::yesterday()->toDateString(),
            'count' => 1.5,
        ]);

        // Ensure old trip is not included (only 2 dates returned)
        $response->assertJsonCount(2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_ignores_trips_with_null_end_time(): void
    {
        Trip::factory()->create([
            'start_time' => Carbon::today()->setHour(10),
            'end_time' => null,
        ])->participants()->attach($this->user->id);

        $this->actingAs($this->user, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl($this->user));

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_list_if_no_trips(): void
    {
        $this->actingAs($this->user, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl($this->user));

        $response->assertOk();
        $response->assertJsonCount(0);
    }
}
