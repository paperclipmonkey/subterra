<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Collection;
use App\Models\Hut;
use App\Models\Permit;
use App\Models\Tag;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Curated caving content used to ground the AI assistant on realistic data.
 *
 * Adds well-known UK caves with proper region / difficulty / style tags, a
 * handful of Yorkshire Dales caving huts (with coordinates), an active
 * permit scheme with bookings, and a few trip reports describing typical
 * conditions. Everything is idempotent — re-running the seeder is safe.
 */
class AssistantDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCuratedCaves();
        $this->seedYorkshireHuts();
        $this->seedPermitWithBookings();
        $this->seedTripReports();
        $demoCaver = $this->seedDemoCaver();
        $this->seedCollections($demoCaver);

        $this->command->info('Seeded curated caves, Yorkshire huts, permit + bookings, trip reports, demo caver, and collections.');
        $this->command->info("Demo caver user ID: {$demoCaver->id}  (use --user={$demoCaver->id} for assistant evals)");
    }

    /**
     * Curated systems chosen to cover every region/difficulty/style combination
     * the assistant evals exercise. Each entry: a system + 1+ caves with coords
     * + tags. Routes are not seeded here — the existing RouteSeeder covers
     * synthetic ones, and Subterra uses freeform grades anyway.
     */
    private function seedCuratedCaves(): void
    {
        $caves = [
            [
                'system' => 'Gaping Gill',
                'system_slug' => 'gaping-gill',
                'description' => "One of the UK's most famous and largest cave systems, with a 110m main shaft and the iconic Main Chamber. Multi-entrance system on the southern flank of Ingleborough — through-trips between Bar Pot, Flood Entrance, and Gaping Gill itself are classic Yorkshire SRT outings.",
                'length' => 16500,
                'vertical_range' => 195,
                'tags' => ['Yorkshire', 'SRT', 'Sporting', 'Hard', 'Through Trip'],
                'entrances' => [
                    ['name' => 'Gaping Gill Main Shaft', 'lat' => 54.1543, 'lng' => -2.3736, 'location_name' => 'Ingleborough, Yorkshire Dales'],
                    ['name' => 'Bar Pot',                 'lat' => 54.1521, 'lng' => -2.3728, 'location_name' => 'Ingleborough, Yorkshire Dales'],
                    ['name' => 'Flood Entrance Pot',      'lat' => 54.1548, 'lng' => -2.3702, 'location_name' => 'Ingleborough, Yorkshire Dales'],
                ],
            ],
            [
                'system' => 'Lancaster Hole — Easegill',
                'system_slug' => 'lancaster-hole-easegill',
                'description' => 'The longest cave system in the UK at over 90km of surveyed passage. The Easegill catchment links Lancaster Hole, County Pot, Pippikin Pot, and many more entrances across Casterton Fell. Outstanding sporting through-trips — but the streamway floods rapidly.',
                'length' => 90000,
                'vertical_range' => 211,
                'tags' => ['Yorkshire', 'SRT', 'Sporting', 'Hard', 'Through Trip', 'Streamway'],
                'entrances' => [
                    ['name' => 'Lancaster Hole', 'lat' => 54.2156, 'lng' => -2.5042, 'location_name' => 'Casterton Fell, Yorkshire Dales'],
                    ['name' => 'County Pot',     'lat' => 54.2192, 'lng' => -2.4969, 'location_name' => 'Casterton Fell, Yorkshire Dales'],
                    ['name' => 'Pippikin Pot',   'lat' => 54.2174, 'lng' => -2.4901, 'location_name' => 'Casterton Fell, Yorkshire Dales'],
                ],
            ],
            [
                'system' => 'Ireby Fell Cavern',
                'system_slug' => 'ireby-fell-cavern',
                'description' => 'A classic Yorkshire SRT trip with a series of fine pitches into a sporting streamway. CNCC permit required.',
                'length' => 1900,
                'vertical_range' => 130,
                'tags' => ['Yorkshire', 'SRT', 'Sporting', 'Streamway', 'Permit'],
                'entrances' => [
                    ['name' => 'Ireby Fell Cavern', 'lat' => 54.2103, 'lng' => -2.4503, 'location_name' => 'Ireby Fell, Yorkshire Dales', 'access_info' => 'Access via CNCC permit only. Apply through cncc.org.uk before the trip.'],
                ],
            ],
            [
                'system' => 'Long Churn Caves',
                'system_slug' => 'long-churn-caves',
                'description' => 'The classic introductory caving trip on Selside. Walking-sized streamway, the famous Cheese Press squeeze (optional), and a connection to Diccan Pot for the bold. Perfect for beginners with a leader.',
                'length' => 1100,
                'vertical_range' => 35,
                'tags' => ['Yorkshire', 'Beginner', 'Streamway', 'No Tackle'],
                'entrances' => [
                    ['name' => 'Upper Long Churn',  'lat' => 54.1745, 'lng' => -2.3268, 'location_name' => 'Selside, Yorkshire Dales'],
                    ['name' => 'Lower Long Churn',  'lat' => 54.1757, 'lng' => -2.3252, 'location_name' => 'Selside, Yorkshire Dales'],
                ],
            ],
            [
                'system' => 'GB Cave',
                'system_slug' => 'gb-cave',
                'description' => 'A Mendip classic — a single-entrance sporting trip down the Bridge, around the Loop, with the option of the Rift to the bottom. CSCC permit; landowner restrictions in spring.',
                'length' => 2300,
                'vertical_range' => 130,
                'tags' => ['Mendip', 'Sporting', 'Permit', 'Streamway'],
                'entrances' => [
                    ['name' => 'GB Cave', 'lat' => 51.2838, 'lng' => -2.7541, 'location_name' => 'Charterhouse, Mendip', 'access_info' => 'CSCC permit required. Closed during the lambing season.'],
                ],
            ],
            [
                'system' => 'Wookey Hole',
                'system_slug' => 'wookey-hole',
                'description' => 'The famous Mendip resurgence — a showcave for tourists and a long sumped system for divers. The dry portion is restricted to the showcave.',
                'length' => 4000,
                'vertical_range' => 70,
                'tags' => ['Mendip', 'Showcave', 'Streamway'],
                'entrances' => [
                    ['name' => 'Wookey Hole', 'lat' => 51.2270, 'lng' => -2.6773, 'location_name' => 'Wookey Hole, Mendip'],
                ],
            ],
            [
                'system' => 'Dan-yr-Ogof',
                'system_slug' => 'dan-yr-ogof',
                'description' => "South Wales' famous showcave. The wild parts beyond the show route are some of the longest passages in the UK and require advanced caving with a guide.",
                'length' => 17000,
                'vertical_range' => 80,
                'tags' => ['South Wales', 'Showcave', 'Streamway', 'Hard'],
                'entrances' => [
                    ['name' => 'Dan-yr-Ogof', 'lat' => 51.8294, 'lng' => -3.6753, 'location_name' => 'Upper Swansea Valley, Brecon Beacons'],
                ],
            ],
            [
                'system' => 'Peak Cavern',
                'system_slug' => 'peak-cavern',
                'description' => 'The Devil\'s Arse — Castleton\'s classic Peak District showcave with sporting wild trips beyond, including the Far Sump bypass and the long crawl into Ink Sump.',
                'length' => 16000,
                'vertical_range' => 95,
                'tags' => ['Peak District', 'Showcave', 'Sporting', 'Streamway'],
                'entrances' => [
                    ['name' => 'Peak Cavern', 'lat' => 53.3429, 'lng' => -1.7775, 'location_name' => 'Castleton, Peak District'],
                ],
            ],
        ];

        foreach ($caves as $entry) {
            $system = CaveSystem::firstOrCreate(
                ['slug' => $entry['system_slug']],
                [
                    'name' => $entry['system'],
                    'description' => $entry['description'],
                    'length' => $entry['length'],
                    'vertical_range' => $entry['vertical_range'],
                ]
            );

            $tagIds = Tag::whereIn('tag', $entry['tags'])->pluck('id')->all();
            $system->tags()->syncWithoutDetaching($tagIds);

            foreach ($entry['entrances'] as $cave) {
                $caveModel = Cave::firstOrCreate(
                    ['slug' => Str::slug($cave['name'])],
                    [
                        'cave_system_id' => $system->id,
                        'name' => $cave['name'],
                        'location_name' => $cave['location_name'],
                        'location_country' => 'United Kingdom',
                        'location_lat' => $cave['lat'],
                        'location_lng' => $cave['lng'],
                        'access_info' => $cave['access_info'] ?? null,
                    ]
                );
                $caveModel->tags()->syncWithoutDetaching($tagIds);
            }
        }
    }

    /**
     * A handful of Yorkshire Dales caving huts. The existing HutSeeder is
     * dominated by Lake District / North Wales mountaineering huts; here we
     * add the well-known Dales caving huts so the assistant can recommend
     * accommodation for trips like Lancaster Hole.
     */
    private function seedYorkshireHuts(): void
    {
        $huts = [
            [
                'name' => 'Bull Pot Farm',
                'lat' => 54.2208,
                'lng' => -2.5025,
                'amenities' => ['Bunks', 'Drying room', 'Kitchen', 'Showers'],
                'booking_info' => 'Book via Red Rose Cave & Pothole Club. Right next to the entrance to Lancaster Hole — a classic base for an Easegill trip.',
                'external_url' => 'https://rrcpc.org.uk/bullpotfarm/',
            ],
            [
                'name' => 'Greenclose',
                'lat' => 54.0922,
                'lng' => -2.3539,
                'amenities' => ['Bunks', 'Drying room', 'Kitchen'],
                'booking_info' => 'Northern Pennine Club\'s hut at Horton-in-Ribblesdale — an excellent base for Penyghent and Ingleborough caving.',
                'external_url' => 'https://npclub.co.uk/',
            ],
            [
                'name' => 'Greenclose East',
                'lat' => 54.0928,
                'lng' => -2.3534,
                'amenities' => ['Bunks', 'Drying room'],
                'booking_info' => 'Bradford Pothole Club hut, Horton-in-Ribblesdale.',
                'external_url' => null,
            ],
            [
                'name' => 'Inglesport Bunkhouse',
                'lat' => 54.1517,
                'lng' => -2.4719,
                'amenities' => ['Bunks', 'Drying room', 'Kitchen', 'Cafe nearby'],
                'booking_info' => 'Above the Inglesport caving shop in Ingleton — handy for cave shop runs and the village.',
                'external_url' => 'https://inglesport.com/',
            ],
            [
                'name' => 'Whernside Manor (Cave Rescue)',
                'lat' => 54.2231,
                'lng' => -2.4011,
                'amenities' => ['Bunks', 'Drying room', 'Training facilities'],
                'booking_info' => 'CRO base — generally for cave rescue training, but enquire for occasional bookings.',
                'external_url' => null,
            ],
        ];

        foreach ($huts as $hut) {
            Hut::firstOrCreate(
                ['name' => $hut['name']],
                [
                    'location_lat' => $hut['lat'],
                    'location_lng' => $hut['lng'],
                    'amenities' => $hut['amenities'],
                    'booking_info' => $hut['booking_info'],
                    'external_url' => $hut['external_url'],
                ]
            );
        }
    }

    /**
     * An active permit scheme on Ireby Fell Cavern with bookings on a couple
     * of upcoming dates and one fully booked date. This lets the assistant
     * answer permit availability questions concretely.
     */
    private function seedPermitWithBookings(): void
    {
        $cave = Cave::where('slug', 'ireby-fell-cavern')->first();
        if (!$cave) {
            $this->command->warn('Ireby Fell Cavern cave not found — skipping permit seed.');

            return;
        }

        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'platform_admin'))->first()
            ?? User::factory()->admin()->create([
                'name' => 'Demo Permit Admin',
                'email' => 'permit-admin@example.com',
            ]);

        $permit = Permit::firstOrCreate(
            ['slug' => 'ireby-fell-cavern-cncc'],
            [
                'name' => 'Ireby Fell Cavern (CNCC)',
                'description' => 'Council of Northern Caving Clubs permit for Ireby Fell Cavern. Apply at least a week in advance.',
                'conditions' => 'No more than 2 groups per day. Maximum group size 6. Leave a callout with CRO. Access via the gated track from the road.',
                'has_max_groups_per_day' => true,
                'max_groups_per_day' => 2,
                'auto_approve' => false,
                'booking_info' => 'You will be sent the gate code and parking instructions on approval. Please respect the landowner.',
                'is_active' => true,
                'created_by' => $admin->id,
            ]
        );

        // Attach the permit to the cave (cave_id is unique on this pivot)
        if (!$cave->permit()->where('permits.id', $permit->id)->exists()) {
            $cave->permit()->attach($permit->id);
        }

        // Seed a few bookings: one single-group day, plus a fully-booked day where
        // two different parties have already taken both available slots.
        $applicantA = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'demo-booking@example.com'],
            ['name' => 'Demo Booking User', 'is_active' => true]
        );
        $applicantB = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'demo-booking-b@example.com'],
            ['name' => 'Demo Booking User B', 'is_active' => true]
        );

        $bookings = [
            ['user' => $applicantA, 'date' => now()->addDays(7)->toDateString()],
            ['user' => $applicantA, 'date' => now()->addDays(14)->toDateString()],
            ['user' => $applicantB, 'date' => now()->addDays(14)->toDateString()],
        ];

        foreach ($bookings as $b) {
            Booking::firstOrCreate(
                [
                    'permit_id' => $permit->id,
                    'user_id' => $b['user']->id,
                    'date' => $b['date'],
                ],
                [
                    'short_id' => Str::lower(Str::random(8)),
                    'participants' => 4,
                    'status' => 'approved',
                    'approved_by' => $admin->id,
                    'approved_at' => now()->subDay(),
                    'conditions_accepted_at' => now()->subDay(),
                ]
            );
        }
    }

    /**
     * A handful of trip reports describing concrete conditions on the curated
     * caves. The assistant uses these via get_cave_system_activity and
     * get_cave_details (recent_reports). Realistic descriptions mean the
     * model has something useful to summarise instead of just a date and a
     * count.
     */
    private function seedTripReports(): void
    {
        $reports = [
            [
                'system_slug' => 'lancaster-hole-easegill',
                'name' => 'Lancaster Hole → County Pot through-trip',
                'days_ago' => 3,
                'duration_h' => 7,
                'description' => "Did the classic Lanc to County through-trip on Saturday. The streamway was higher than usual after Tuesday's rain — Stake Pot was knee-deep with strong flow and Stop Pot took some shouting to communicate over. Wouldn't recommend the streamway route in current conditions; the high-level traverse is the smarter call until levels drop. SRT pitches all in good order, ropes recently replaced.",
            ],
            [
                'system_slug' => 'lancaster-hole-easegill',
                'name' => 'Pippikin Pot — straight up and out',
                'days_ago' => 18,
                'duration_h' => 5,
                'description' => 'Bounce trip down Pippikin to the bottom of the entrance series and back. Tight in places as expected, but very dry. Worth doing while conditions hold — the streamway downstream from Hall of the Ten was barely flowing.',
            ],
            [
                'system_slug' => 'gaping-gill',
                'name' => 'Gaping Gill via Bar Pot',
                'days_ago' => 9,
                'duration_h' => 6,
                'description' => "Fantastic day. Rigged Bar Pot with new club rope, abseiled into Main Chamber via the East Slope. Even in dry weather there's a respectable shower coming off the Main Shaft — wear waterproofs. T-piece rigging on the second pitch wears the rope quickly so check it before reusing. Took 5 hours round trip.",
            ],
            [
                'system_slug' => 'long-churn-caves',
                'name' => 'Beginner trip with new club members',
                'days_ago' => 4,
                'duration_h' => 2,
                'description' => 'Took two new members through Upper Long Churn, the Cheese Press, then exited via Lower. Streamway was lively but well within reasonable limits — knee-deep at most. Brilliant introductory trip; everyone loved the Cheese Press. Total trip 1h45.',
            ],
            [
                'system_slug' => 'gb-cave',
                'name' => 'GB Cave — round trip',
                'days_ago' => 21,
                'duration_h' => 4,
                'description' => 'Round trip via Gorge to the Bridge, down to the Bat Passage, and out via the Loop. Dry conditions — mud-baths in the lower series exactly as expected. CSCC permit went through smoothly via the website.',
            ],
            [
                'system_slug' => 'ireby-fell-cavern',
                'name' => 'Ireby Fell Cavern',
                'days_ago' => 12,
                'duration_h' => 5,
                'description' => "Permit came through within 24 hours via CNCC. Perfect rigging job — pulley jam on the third pitch made it slightly awkward but otherwise straightforward. The streamway at the bottom was exciting in a good way; wouldn't fancy it after heavy rain.",
            ],
        ];

        foreach ($reports as $r) {
            $system = CaveSystem::where('slug', $r['system_slug'])->first();
            if (!$system) {
                continue;
            }

            $entrance = $system->caves()->whereNotNull('location_lat')->first();
            $start = now()->subDays($r['days_ago'])->setTime(10, 0);
            $end = $start->copy()->addHours($r['duration_h']);

            Trip::firstOrCreate(
                [
                    'cave_system_id' => $system->id,
                    'name' => $r['name'],
                    'start_time' => $start,
                ],
                [
                    'description' => $r['description'],
                    'end_time' => $end,
                    'entrance_cave_id' => $entrance?->id,
                    'exit_cave_id' => $entrance?->id,
                    'visibility' => 'public',
                ]
            );
        }
    }

    /**
     * Create or refresh "Demo Caver" — a user with a realistic mix of trip
     * history across difficulty levels and regions. The assistant uses this
     * via get_user_experience to make personalised recommendations, so a thin
     * trip log gives generic advice. The trips here are private to this user
     * and won't pollute the public trip-report stream.
     */
    private function seedDemoCaver(): User
    {
        $user = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'demo-caver@example.com'],
            ['name' => 'Demo Caver', 'is_active' => true]
        );

        // Each entry: cave system slug, days ago (relative), duration in hours,
        // optional name. Mix chosen to give the assistant clear signal:
        //   - Done plenty of intro / sporting Mendip & Yorkshire trips
        //   - Has SRT exposure (Lancaster Hole, Bar Pot)
        //   - Has not yet done the very hard trips (Dan-yr-Ogof wild, Ireby Fell)
        //   - Has not yet visited Peak District or South Wales
        $tripLog = [
            ['system' => 'long-churn-caves',          'days' => 540, 'hours' => 2,  'name' => 'First-ever caving trip'],
            ['system' => 'long-churn-caves',          'days' => 510, 'hours' => 2,  'name' => 'Long Churn with new club members'],
            ['system' => 'gb-cave',                   'days' => 460, 'hours' => 4,  'name' => 'GB Cave Bridge route'],
            ['system' => 'wookey-hole',               'days' => 420, 'hours' => 1,  'name' => 'Wookey Hole showcave visit'],
            ['system' => 'gaping-gill',               'days' => 360, 'hours' => 6,  'name' => 'Gaping Gill via Bar Pot — first SRT'],
            ['system' => 'long-churn-caves',          'days' => 320, 'hours' => 2,  'name' => 'Cheese Press round trip'],
            ['system' => 'gb-cave',                   'days' => 280, 'hours' => 5,  'name' => 'GB Cave round trip + Loop'],
            ['system' => 'gaping-gill',               'days' => 220, 'hours' => 7,  'name' => 'Gaping Gill via Flood Entrance'],
            ['system' => 'lancaster-hole-easegill',   'days' => 180, 'hours' => 8,  'name' => 'Lancaster Hole — first sporting through-trip'],
            ['system' => 'gb-cave',                   'days' => 130, 'hours' => 4,  'name' => 'GB Cave with visiting club'],
            ['system' => 'lancaster-hole-easegill',   'days' => 95,  'hours' => 6,  'name' => 'County Pot bounce trip'],
            ['system' => 'gaping-gill',               'days' => 60,  'hours' => 7,  'name' => 'Gaping Gill main winch meet'],
            ['system' => 'long-churn-caves',          'days' => 30,  'hours' => 2,  'name' => 'Took friends down Long Churn'],
        ];

        foreach ($tripLog as $entry) {
            $system = CaveSystem::where('slug', $entry['system'])->first();
            if (!$system) {
                continue;
            }
            $entrance = $system->caves()->whereNotNull('location_lat')->first();
            $start = now()->subDays($entry['days'])->setTime(10, 0);
            $end = $start->copy()->addHours($entry['hours']);

            $trip = Trip::firstOrCreate(
                [
                    'cave_system_id' => $system->id,
                    'name' => $entry['name'],
                    'start_time' => $start,
                ],
                [
                    'description' => null,
                    'end_time' => $end,
                    'entrance_cave_id' => $entrance?->id,
                    'exit_cave_id' => $entrance?->id,
                    'visibility' => 'private',
                ]
            );

            // Attach the demo caver as a participant (idempotent)
            $trip->participants()->syncWithoutDetaching([$user->id]);
        }

        return $user;
    }

    /**
     * Curated collections users can browse and tick off as goals. The assistant
     * surfaces these via list_collections / get_collection_details and reports
     * the user's progress (visited / total).
     */
    private function seedCollections(User $owner): void
    {
        $collections = [
            [
                'slug' => 'yorkshire-big-three',
                'name' => 'Yorkshire Big Three',
                'description' => "Three classic Yorkshire systems every caver should aim for: Gaping Gill's Main Chamber, the through-trip into Easegill from Lancaster Hole, and a sporting bounce down Pippikin Pot.",
                'caves' => ['gaping-gill-main-shaft', 'lancaster-hole', 'pippikin-pot'],
            ],
            [
                'slug' => 'mendip-classics',
                'name' => 'Mendip Classics',
                'description' => "The defining Mendip trips: Swildon's streamway, GB Cave's sporting round trip, and the famous resurgence at Wookey Hole.",
                'caves' => ['swildons-hole', 'gb-cave', 'wookey-hole'],
            ],
            [
                'slug' => 'first-caves-underground',
                'name' => 'First Caves Underground',
                'description' => 'Excellent introductory trips for cavers new to the sport. Walking-sized passage, simple navigation, and very low commitment — perfect for building confidence with a leader.',
                'caves' => ['upper-long-churn', 'lower-long-churn'],
            ],
            [
                'slug' => 'south-wales-streamways',
                'name' => 'South Wales Streamways',
                'description' => "South Wales' two giants — Ogof Ffynnon Ddu (the deepest in the UK) and Dan-yr-Ogof — each with classic streamway sections that demand respect for water levels.",
                'caves' => ['dan-yr-ogof'],
            ],
        ];

        foreach ($collections as $entry) {
            $collection = Collection::firstOrCreate(
                ['slug' => $entry['slug']],
                [
                    'name' => $entry['name'],
                    'description' => $entry['description'],
                    'user_id' => $owner->id,
                ]
            );

            $caveIds = Cave::whereIn('slug', $entry['caves'])
                ->pluck('id', 'slug');

            // Preserve the order from the entry list via sort_order
            $sync = [];
            foreach ($entry['caves'] as $idx => $caveSlug) {
                if (isset($caveIds[$caveSlug])) {
                    $sync[$caveIds[$caveSlug]] = ['sort_order' => $idx];
                }
            }

            if (!empty($sync)) {
                $collection->caves()->syncWithoutDetaching($sync);
            }
        }
    }
}
