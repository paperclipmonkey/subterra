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

    #[\PHPUnit\Framework\Attributes\Test]
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

    #[\PHPUnit\Framework\Attributes\Test]
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

    #[\PHPUnit\Framework\Attributes\Test]
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

    #[\PHPUnit\Framework\Attributes\Test]
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

    #[\PHPUnit\Framework\Attributes\Test]
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_ham_pasta_aficionado_medal_for_hunters_hole_and_lodge_inn_sink()
    {
        $user = \App\Models\User::factory()->create();
        $medal = \App\Models\Medal::create([
            'name' => 'Ham pasta aficionado',
            'description' => 'Awarded for doing Hunters Hole and Hunters Lodge Inn Sink',
        ]);
        $huntersHole = \App\Models\Cave::factory()->create(['name' => 'Hunters\' Hole']);
        $huntersLodge = \App\Models\Cave::factory()->create(['name' => 'Hunters\' Lodge Inn Sink']);
        $trip1 = \App\Models\Trip::factory()->create(['entrance_cave_id' => $huntersHole->id]);
        $trip2 = \App\Models\Trip::factory()->create(['entrance_cave_id' => $huntersLodge->id]);
        $trip1->participants()->attach($user);
        $trip2->participants()->attach($user);
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip2, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Ham pasta aficionado'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_hard_caver_medal_for_trips_in_yorkshire_mendip_and_wales()
    {
        $user = \App\Models\User::factory()->create();
        $medal = \App\Models\Medal::create([
            'name' => 'Hard Caver',
            'description' => 'Awarded for trips in Yorkshire, Mendip and Wales',
        ]);
        $yorkshireTag = \App\Models\Tag::factory()->create(['tag' => 'Yorkshire', 'category' => 'region', 'type' => 'cave']);
        $mendipTag = \App\Models\Tag::factory()->create(['tag' => 'Mendip', 'category' => 'region', 'type' => 'cave']);
        $walesTag = \App\Models\Tag::factory()->create(['tag' => 'Wales', 'category' => 'region', 'type' => 'cave']);
        $cave1 = \App\Models\Cave::factory()->create();
        $cave1->tags()->attach($yorkshireTag);
        $cave2 = \App\Models\Cave::factory()->create();
        $cave2->tags()->attach($mendipTag);
        $cave3 = \App\Models\Cave::factory()->create();
        $cave3->tags()->attach($walesTag);
        $trip1 = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave1->id]);
        $trip2 = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave2->id]);
        $trip3 = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave3->id]);
        $trip1->participants()->attach($user);
        $trip2->participants()->attach($user);
        $trip3->participants()->attach($user);
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip3, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Hard Caver'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_history_buff_medal_for_five_mines()
    {
        $user = \App\Models\User::factory()->create();
        $medal = \App\Models\Medal::create([
            'name' => 'History Buff',
            'description' => 'Awarded for doing 5 mines',
        ]);
        $mineTag = \App\Models\Tag::factory()->create(['tag' => 'Mine', 'category' => 'type', 'type' => 'cave']);
        for ($i = 0; $i < 5; $i++) {
            $cave = \App\Models\Cave::factory()->create();
            $cave->tags()->attach($mineTag);
            $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
            $trip->participants()->attach($user);
        }
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'History Buff'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_sport_climber_medal_for_caving_in_portland()
    {
        $user = \App\Models\User::factory()->create();
        $medal = \App\Models\Medal::create([
            'name' => 'Sport Climber',
            'description' => 'Awarded for caving in Portland',
        ]);
        $portlandTag = \App\Models\Tag::factory()->create(['tag' => 'Portland', 'category' => 'region', 'type' => 'cave']);
        $cave = \App\Models\Cave::factory()->create();
        $cave->tags()->attach($portlandTag);
        $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
        $trip->participants()->attach($user);
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Sport Climber'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_cream_tea_medal_for_caving_in_devon()
    {
        $user = \App\Models\User::factory()->create();
        $medal = \App\Models\Medal::create([
            'name' => 'Cream Tea',
            'description' => 'Awarded for caving in Devon',
        ]);
        $devonTag = \App\Models\Tag::factory()->create(['tag' => 'Devon', 'category' => 'region', 'type' => 'cave']);
        $cave = \App\Models\Cave::factory()->create();
        $cave->tags()->attach($devonTag);
        $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
        $trip->participants()->attach($user);
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Cream Tea'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_highland_cow_medal_for_caving_in_scotland()
    {
        $user = \App\Models\User::factory()->create();
        $medal = \App\Models\Medal::create([
            'name' => 'Highland Cow',
            'description' => 'Awarded for caving in Scotland',
        ]);
        $scotlandTag = \App\Models\Tag::factory()->create(['tag' => 'Scotland', 'category' => 'region', 'type' => 'cave']);
        $cave = \App\Models\Cave::factory()->create();
        $cave->tags()->attach($scotlandTag);
        $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
        $trip->participants()->attach($user);
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Highland Cow'));
    }
}