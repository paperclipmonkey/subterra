<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\TripParticipantTagged;
use App\Listeners\CheckAndAwardMedals;
use App\Models\Medal;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

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
        for ($i = 0; $i < 5; ++$i) {
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
        for ($i = 0; $i < 20; ++$i) {
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
        $northernTag = \App\Models\Tag::factory()->create(['tag' => 'Northern', 'category' => 'region', 'type' => 'cave']);
        $mendipTag = \App\Models\Tag::factory()->create(['tag' => 'Mendip', 'category' => 'region', 'type' => 'cave']);
        $walesTag = \App\Models\Tag::factory()->create(['tag' => 'Wales', 'category' => 'region', 'type' => 'cave']);
        $cave1 = \App\Models\Cave::factory()->create();
        $cave1->tags()->attach($northernTag);
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
        for ($i = 0; $i < 5; ++$i) {
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_sheep_dog_medal_for_five_leader_systems()
    {
        $user = \App\Models\User::factory()->create();
        $medal = \App\Models\Medal::create([
            'name' => 'Sheep dog',
            'description' => 'Awarded for going on 5 trips to leader systems',
        ]);
        $leaderTag = \App\Models\Tag::factory()->create(['tag' => 'Warden', 'category' => 'type', 'type' => 'cave']);
        for ($i = 0; $i < 5; ++$i) {
            $cave = \App\Models\Cave::factory()->create();
            $cave->tags()->attach($leaderTag);
            $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
            $trip->participants()->attach($user);
        }
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Sheep dog'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_mucky_pup_medal_for_three_muddy_caves()
    {
        $user = \App\Models\User::factory()->create();
        $medal = \App\Models\Medal::create([
            'name' => 'Mucky Pup',
            'description' => 'Awarded for going to 3 muddy caves',
        ]);
        $muddyTag = \App\Models\Tag::factory()->create(['tag' => 'Muddy', 'category' => 'conditions', 'type' => 'cave_system']);
        for ($i = 0; $i < 3; ++$i) {
            $cave = \App\Models\Cave::factory()->create();
            $cave->system->tags()->attach($muddyTag);
            $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
            $trip->participants()->attach($user);
        }
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Mucky Pup'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_faff_now_cave_later_medal_for_five_swcc_trips()
    {
        $user = \App\Models\User::factory()->create();
        \App\Models\Medal::create([
            'name' => 'Faff Now Cave Later',
            'description' => 'Awarded for going on 5 trips',
        ]);
        $swccCaveNames = ['Ogof Ffynnon Ddu 1', 'Ogof Ffynnon Ddu 2', 'Cwm Dwr'];
        foreach ($swccCaveNames as $caveName) {
            $cave = \App\Models\Cave::factory()->create(['name' => $caveName]);
            $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
            $trip->participants()->attach($user);
        }

        foreach ($swccCaveNames as $caveName) {
            $cave = \App\Models\Cave::factory()->create(['name' => $caveName]);
            $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
            $trip->participants()->attach($user);
        }
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Faff Now Cave Later'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_string_dangler_medal_for_ten_srt_trips()
    {
        $user = \App\Models\User::factory()->create();
        $medal = \App\Models\Medal::create([
            'name' => 'String Dangler',
            'description' => 'Awarded for going on 10 trips to entrances with the tag SRT',
        ]);
        $srtTag = \App\Models\Tag::factory()->create(['tag' => 'SRT', 'category' => 'type', 'type' => 'cave']);
        for ($i = 0; $i < 10; ++$i) {
            $cave = \App\Models\Cave::factory()->create();
            $cave->tags()->attach($srtTag);
            $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
            $trip->participants()->attach($user);
        }
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'String Dangler'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_copper_miner_medal_for_caving_at_great_orme()
    {
        $user = \App\Models\User::factory()->create();
        \App\Models\Medal::create([
            'name' => 'Copper Miner',
            'description' => 'Awarded for caving at the Great Orme',
        ]);
        $tag = \App\Models\Tag::factory()->create(['tag' => 'Great Orme', 'category' => 'region', 'type' => 'cave']);
        $cave = \App\Models\Cave::factory()->create();
        $cave->tags()->attach($tag);
        $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
        $trip->participants()->attach($user);
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Copper Miner'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_archivist_medal_for_submitting_a_suggested_edit()
    {
        $user = \App\Models\User::factory()->create();
        \App\Models\Medal::create([
            'name' => 'Archivist',
            'description' => 'Awarded for submitting a suggested edit to improve the cave data.',
        ]);
        $cave = \App\Models\Cave::factory()->create();
        \App\Models\SuggestedEdit::create([
            'user_id' => $user->id,
            'suggestable_type' => \App\Models\Cave::class,
            'suggestable_id' => $cave->id,
            'suggested_data' => ['description' => 'Better description'],
            'status' => 'pending',
        ]);

        $listener = new \App\Listeners\CheckAndAwardMedals();
        $listener->handle(new \App\Events\UserContributed($user));

        $this->assertTrue($user->fresh()->medals->contains('name', 'Archivist'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_does_not_get_archivist_medal_without_a_suggested_edit()
    {
        $user = \App\Models\User::factory()->create();
        \App\Models\Medal::create([
            'name' => 'Archivist',
            'description' => 'Awarded for submitting a suggested edit to improve the cave data.',
        ]);

        $listener = new \App\Listeners\CheckAndAwardMedals();
        $listener->handle(new \App\Events\UserContributed($user));

        $this->assertFalse($user->fresh()->medals->contains('name', 'Archivist'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_cave_photographer_medal_for_photos_on_three_trips()
    {
        $user = \App\Models\User::factory()->create();
        \App\Models\Medal::create([
            'name' => 'Cave Photographer',
            'description' => 'Awarded for adding photos to 3 of your trips.',
        ]);
        for ($i = 0; $i < 3; ++$i) {
            $trip = \App\Models\Trip::factory()->create();
            $trip->participants()->attach($user);
            \App\Models\TripMedia::factory()->create(['trip_id' => $trip->id]);
        }
        // A photo-less trip shouldn't count
        $bareTrip = \App\Models\Trip::factory()->create();
        $bareTrip->participants()->attach($user);

        $listener = new \App\Listeners\CheckAndAwardMedals();
        $listener->handle(new \App\Events\TripParticipantTagged($bareTrip, $user, $user));

        $this->assertTrue($user->fresh()->medals->contains('name', 'Cave Photographer'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_wordsmith_medal_for_five_detailed_trip_reports()
    {
        $user = \App\Models\User::factory()->create();
        \App\Models\Medal::create([
            'name' => 'Wordsmith',
            'description' => 'Awarded for writing 5 detailed trip reports.',
        ]);
        $longDescription = str_repeat('A grand day underground with plenty to report. ', 10);
        for ($i = 0; $i < 5; ++$i) {
            $trip = \App\Models\Trip::factory()->create(['description' => $longDescription]);
            $trip->participants()->attach($user);
        }
        // Short write-ups shouldn't count
        $shortTrip = \App\Models\Trip::factory()->create(['description' => 'Good trip.']);
        $shortTrip->participants()->attach($user);

        $listener = new \App\Listeners\CheckAndAwardMedals();
        $listener->handle(new \App\Events\TripParticipantTagged($shortTrip, $user, $user));

        $this->assertTrue($user->fresh()->medals->contains('name', 'Wordsmith'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_does_not_get_wordsmith_medal_for_short_reports()
    {
        $user = \App\Models\User::factory()->create();
        \App\Models\Medal::create([
            'name' => 'Wordsmith',
            'description' => 'Awarded for writing 5 detailed trip reports.',
        ]);
        for ($i = 0; $i < 5; ++$i) {
            $trip = \App\Models\Trip::factory()->create(['description' => 'Short note.']);
            $trip->participants()->attach($user);
        }

        $listener = new \App\Listeners\CheckAndAwardMedals();
        $listener->handle(new \App\Events\TripParticipantTagged($trip, $user, $user));

        $this->assertFalse($user->fresh()->medals->contains('name', 'Wordsmith'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_copper_miner_medal_for_great_orme_cave_named_by_location()
    {
        $user = \App\Models\User::factory()->create();
        \App\Models\Medal::create([
            'name' => 'Copper Miner',
            'description' => 'Awarded for caving at the Great Orme',
        ]);
        // No region tag — the location only appears in the cave name
        $cave = \App\Models\Cave::factory()->create(['name' => 'Penmorfa, Llandudno, Wales']);
        $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
        $trip->participants()->attach($user);
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Copper Miner'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_dragons_lair_medal_for_five_welsh_trips()
    {
        $user = \App\Models\User::factory()->create();
        \App\Models\Medal::create([
            'name' => "Dragon's Lair",
            'description' => 'Awarded for 5 trips to Welsh caves',
        ]);
        $walesTag = \App\Models\Tag::factory()->create(['tag' => 'Wales', 'category' => 'region', 'type' => 'cave']);
        for ($i = 0; $i < 5; ++$i) {
            $cave = \App\Models\Cave::factory()->create();
            $cave->tags()->attach($walesTag);
            $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
            $trip->participants()->attach($user);
        }
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', "Dragon's Lair"));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_completionist_medal_for_completing_a_cave_collection()
    {
        $user = \App\Models\User::factory()->create();
        \App\Models\Medal::create([
            'name' => 'Completionist',
            'description' => 'Awarded for completing any cave collection',
        ]);
        $cave1 = \App\Models\Cave::factory()->create();
        $cave2 = \App\Models\Cave::factory()->create();
        $collection = \App\Models\Collection::factory()->create();
        $collection->caves()->attach([$cave1->id, $cave2->id]);
        $trip1 = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave1->id]);
        $trip2 = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave2->id]);
        $trip1->participants()->attach($user);
        $trip2->participants()->attach($user);
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip2, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Completionist'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_does_not_get_completionist_medal_for_incomplete_collection()
    {
        $user = \App\Models\User::factory()->create();
        \App\Models\Medal::create([
            'name' => 'Completionist',
            'description' => 'Awarded for completing any cave collection',
        ]);
        $cave1 = \App\Models\Cave::factory()->create();
        $cave2 = \App\Models\Cave::factory()->create();
        $collection = \App\Models\Collection::factory()->create();
        $collection->caves()->attach([$cave1->id, $cave2->id]);
        // User only visits cave1, not cave2
        $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave1->id]);
        $trip->participants()->attach($user);
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertFalse($user->fresh()->medals->contains('name', 'Completionist'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function concurrent_duplicate_award_is_ignored_and_other_medals_still_fire_events()
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\MedalAwarded::class]);

        $user = User::factory()->create();
        $firstTrip = Medal::create(['name' => 'First Trip', 'description' => 'Awarded for your first trip!']);
        $nightOwl = Medal::create(['name' => 'Night Owl', 'description' => 'Trip started after 8pm']);

        $trip = Trip::factory()->create(['start_time' => Carbon::parse('2025-04-24 21:00:00')]);
        $trip->participants()->attach($user);

        // Hydrate the medals relation as empty BEFORE the concurrent award so
        // the listener's "not yet earned" check passes — mirroring the race
        // where two workers both pass the check for the same medal.
        $user->medals;
        \Illuminate\Support\Facades\DB::table('medal_user')->insert([
            'user_id' => $user->id,
            'medal_id' => $firstTrip->id,
            'awarded_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $listener = new CheckAndAwardMedals();
        $listener->handle(new TripParticipantTagged($trip, $user, $user));

        // The duplicate attach is swallowed and the loop carries on, so the
        // second medal is still awarded and announced.
        $this->assertTrue($user->fresh()->medals->contains('name', 'Night Owl'));
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\MedalAwarded::class, function ($event) use ($nightOwl) {
            return $event->medal->id === $nightOwl->id;
        });
        // The concurrent worker owns the First Trip announcement — no duplicate event
        \Illuminate\Support\Facades\Event::assertNotDispatched(\App\Events\MedalAwarded::class, function ($event) use ($firstTrip) {
            return $event->medal->id === $firstTrip->id;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function medal_awarded_email_respects_email_trophies_preference()
    {
        $medal = Medal::create(['name' => 'First Trip', 'description' => 'Awarded for your first trip!']);
        $optedOut = User::factory()->create(['email_trophies' => false]);
        $optedIn = User::factory()->create(['email_trophies' => true]);

        $listener = new \App\Listeners\SendMedalAwardedNotification();
        $listener->handle(new \App\Events\MedalAwarded($optedOut, $medal));
        $listener->handle(new \App\Events\MedalAwarded($optedIn, $medal));

        Mail::assertQueued(\App\Mail\MedalAwardedMail::class, function ($mail) use ($optedIn) {
            return $mail->hasTo($optedIn->email);
        });
        Mail::assertNotQueued(\App\Mail\MedalAwardedMail::class, function ($mail) use ($optedOut) {
            return $mail->hasTo($optedOut->email);
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_slate_heart_medal_for_caving_in_north_wales()
    {
        $user = \App\Models\User::factory()->create();
        \App\Models\Medal::create([
            'name' => 'Slate Heart',
            'description' => 'Awarded for caving in North Wales',
        ]);
        $tag = \App\Models\Tag::factory()->create(['tag' => 'North Wales', 'category' => 'region', 'type' => 'cave']);
        $cave = \App\Models\Cave::factory()->create();
        $cave->tags()->attach($tag);
        $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
        $trip->participants()->attach($user);
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Slate Heart'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_gower_power_medal_for_caving_in_gower()
    {
        $user = \App\Models\User::factory()->create();
        \App\Models\Medal::create([
            'name' => 'Gower Power',
            'description' => 'Awarded for caving in Gower',
        ]);
        $tag = \App\Models\Tag::factory()->create(['tag' => 'Gower', 'category' => 'region', 'type' => 'cave']);
        $cave = \App\Models\Cave::factory()->create();
        $cave->tags()->attach($tag);
        $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
        $trip->participants()->attach($user);
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Gower Power'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_free_miner_medal_for_caving_in_forest_of_dean()
    {
        $user = \App\Models\User::factory()->create();
        \App\Models\Medal::create([
            'name' => 'Free Miner',
            'description' => 'Awarded for caving in the Forest of Dean',
        ]);
        $tag = \App\Models\Tag::factory()->create(['tag' => 'Forest of Dean', 'category' => 'region', 'type' => 'cave']);
        $cave = \App\Models\Cave::factory()->create();
        $cave->tags()->attach($tag);
        $trip = \App\Models\Trip::factory()->create(['entrance_cave_id' => $cave->id]);
        $trip->participants()->attach($user);
        $listener = new \App\Listeners\CheckAndAwardMedals();
        $event = new \App\Events\TripParticipantTagged($trip, $user, $user);
        $listener->handle($event);
        $this->assertTrue($user->fresh()->medals->contains('name', 'Free Miner'));
    }
}
