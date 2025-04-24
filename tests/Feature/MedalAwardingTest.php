<?php

namespace Tests\Feature;

use App\Events\TripParticipantTagged;
use App\Listeners\CheckAndAwardMedals;
use App\Models\Medal;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Carbon\Carbon;

class MedalAwardingTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /** @test */
    public function user_gets_first_trip_medal_on_first_trip()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $trip->participants()->attach($user);
        $medal = Medal::create(['name' => 'First Trip', 'description' => 'Awarded for your first trip!']);

        $listener = new CheckAndAwardMedals();
        $event = new TripParticipantTagged($trip, $user, $user); // creator doesn't matter for logic
        $listener->handle($event);

        $this->assertTrue($user->fresh()->medals->contains('name', 'First Trip'));
    }

    /** @test */
    public function user_gets_explorer_medal_for_five_unique_caves()
    {
        $user = User::factory()->create();
        $medal = Medal::create(['name' => 'Explorer', 'description' => 'Visit 5 different caves']);
        $caveIds = [];
        for ($i = 0; $i < 5; $i++) {
            $cave = \App\Models\Cave::factory()->create();
            $caveIds[] = $cave->id;
            $trip = Trip::factory()->create(['entrance_cave_id' => $cave->id]);
            $trip->participants()->attach($user);
        }
        $listener = new CheckAndAwardMedals();
        $event = new TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Explorer'));
    }

    /** @test */
    public function user_gets_veteran_medal_for_twenty_trips()
    {
        $user = User::factory()->create();
        $medal = Medal::create(['name' => 'Veteran', 'description' => 'Participate in 20 trips']);
        for ($i = 0; $i < 20; $i++) {
            $trip = Trip::factory()->create();
            $trip->participants()->attach($user);
        }
        $listener = new CheckAndAwardMedals();
        $event = new TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Veteran'));
    }

    /** @test */
    public function user_gets_night_owl_medal_for_night_trip()
    {
        $user = User::factory()->create();
        $medal = Medal::create(['name' => 'Night Owl', 'description' => 'Trip started after 8pm']);
        $trip = Trip::factory()->create(['start_time' => Carbon::parse('2025-04-24 21:00:00')]);
        $trip->participants()->attach($user);
        $listener = new CheckAndAwardMedals();
        $event = new TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Night Owl'));
    }

    /** @test */
    public function user_gets_through_trip_medal_for_different_entrance_and_exit()
    {
        $user = User::factory()->create();
        $medal = Medal::create(['name' => 'Through Trip', 'description' => 'Entrance and exit caves are different']);
        $entrance = \App\Models\Cave::factory()->create();
        $exit = \App\Models\Cave::factory()->create();
        $trip = Trip::factory()->create(['entrance_cave_id' => $entrance->id, 'exit_cave_id' => $exit->id]);
        $trip->participants()->attach($user);
        $listener = new CheckAndAwardMedals();
        $event = new TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Through Trip'));
    }
}
