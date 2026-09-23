<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\SuggestedEdit;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class SyncOsmCavesTest extends TestCase
{
    use RefreshDatabase;

    private const OVERPASS_PATTERN = '*overpass-api.de*';

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();

        Tag::firstOrCreate(['tag' => 'Cave', 'category' => 'type'], ['type' => 'cave']);
        Tag::firstOrCreate(['tag' => 'Northern', 'category' => 'region'], ['type' => 'cave']);
        Tag::firstOrCreate(['tag' => 'Peak District', 'category' => 'region'], ['type' => 'cave']);
    }

    private function fakeDefaultHttp(): void
    {
        Http::fake([
            self::OVERPASS_PATTERN => Http::response($this->mockOverpass(), 200),
        ]);
    }

    /**
     * A representative Overpass payload: two named caves in different regions,
     * one with elevation, one unnamed node, one blocklist target, and one cave
     * outside every region box.
     *
     * @return array<string, mixed>
     */
    private function mockOverpass(): array
    {
        return [
            'version' => 0.6,
            'elements' => [
                [
                    'type' => 'node',
                    'id' => 1001,
                    'lat' => 54.17545399624298,
                    'lon' => -2.3464968212268102,
                    'tags' => ['natural' => 'cave_entrance', 'name' => 'Alum Pot'],
                ],
                [
                    'type' => 'node',
                    'id' => 1002,
                    'lat' => 53.341700,
                    'lon' => -1.777000,
                    'tags' => ['natural' => 'cave_entrance', 'name' => 'Peak Cavern', 'ele' => '305'],
                ],
                [
                    'type' => 'node',
                    'id' => 1003,
                    'lat' => 54.180000,
                    'lon' => -2.350000,
                    'tags' => ['natural' => 'cave_entrance'], // unnamed
                ],
                [
                    'type' => 'node',
                    'id' => 1004,
                    'lat' => 54.190000,
                    'lon' => -2.360000,
                    'tags' => ['natural' => 'cave_entrance', 'name' => 'Blocked Cave'],
                ],
                [
                    'type' => 'node',
                    'id' => 1005,
                    'lat' => 51.501400, // central London — outside every region box
                    'lon' => -0.141900,
                    'tags' => ['natural' => 'cave_entrance', 'name' => 'Nowhere Hole'],
                ],
            ],
        ];
    }

    // -----------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_named_osm_caves(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Alum Pot']);
        $this->assertDatabaseHas('caves', ['name' => 'Peak Cavern']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_unnamed_entrances_by_default(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        // node 1003 has no name — should not produce a cave
        $this->assertDatabaseMissing('caves', ['registry_id' => '1003']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_unnamed_entrances_when_flagged(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves --include-unnamed')->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['registry_id' => '1003']);
        $this->assertDatabaseHas('caves', ['name' => 'Cave entrance #1003']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_registry_and_osm_node_id(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        $cave = Cave::where('name', 'Alum Pot')->firstOrFail();
        $this->assertEquals('osm', $cave->registry);
        $this->assertEquals('1001', $cave->registry_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_osm_slug_prefix(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Alum Pot', 'slug' => 'osm_alum-pot']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_region_tags_from_coordinates(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        $alum = Cave::where('name', 'Alum Pot')->firstOrFail();
        $this->assertContains('Northern', $alum->tags->pluck('tag')->all());
        $this->assertContains('Cave', $alum->tags->pluck('tag')->all());

        $peak = Cave::where('name', 'Peak Cavern')->firstOrFail();
        $this->assertContains('Peak District', $peak->tags->pluck('tag')->all());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_caves_outside_known_regions_without_a_region_tag(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        $cave = Cave::where('name', 'Nowhere Hole')->firstOrFail();
        $regionTags = $cave->tags->where('category', 'region')->pluck('tag')->all();
        $this->assertEmpty($regionTags);
        $this->assertSame('', $cave->location_name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_elevation_into_location_alt(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        $cave = Cave::where('name', 'Peak Cavern')->firstOrFail();
        $this->assertEqualsWithDelta(305.0, (float) $cave->location_alt, 0.001);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_odbl_attribution_and_osm_link_to_description(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        $cave = Cave::where('name', 'Alum Pot')->firstOrFail();
        $this->assertStringContainsString('openstreetmap.org/node/1001', $cave->description);
        $this->assertStringContainsString('OpenStreetMap contributors', $cave->description);
        $this->assertStringContainsString('ODbL', $cave->description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_osm_reference_link_to_cave_system(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        $system = CaveSystem::where('name', 'Alum Pot')->firstOrFail();
        $this->assertStringContainsString('openstreetmap.org/node/1001', $system->references);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_respects_the_blocklist(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves --blocklist="Blocked Cave"')->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Alum Pot']);
        $this->assertDatabaseMissing('caves', ['name' => 'Blocked Cave']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_import_data_in_dry_run_mode(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves --dry-run')->assertExitCode(0);

        $this->assertDatabaseCount('caves', 0);
        $this->assertDatabaseCount('cave_systems', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_only_links_a_cave_already_held_by_another_registry(): void
    {
        $this->fakeDefaultHttp();

        // An existing CNCC cave for Alum Pot, within 10km of the OSM node but with
        // different coordinates and no description.
        $system = CaveSystem::factory()->create(['name' => 'Alum Pot', 'slug' => 'alum-pot', 'references' => null]);
        $cave = Cave::factory()->create([
            'name' => 'Alum Pot',
            'cave_system_id' => $system->id,
            'registry' => 'cncc',
            'registry_id' => 'alum-pot',
            'description' => '',
            'location_lat' => 54.17000,
            'location_lng' => -2.34000,
        ]);

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        // One Alum Pot, still CNCC-owned, linked to its OSM node…
        $this->assertSame(1, Cave::where('name', 'Alum Pot')->count());
        $cave->refresh();
        $this->assertSame('cncc', $cave->registry);
        $this->assertSame('1001', $cave->osm_node_id);

        // …and no OSM data proposed for it or its system (ODbL data kept separate).
        $this->assertDatabaseCount('suggested_edits', 0);
        $this->assertEquals(54.17000, (float) $cave->location_lat);
        $this->assertNull($system->fresh()->references);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_is_idempotent_on_a_second_run(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves')->assertExitCode(0);
        $countAfterFirst = Cave::count();

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        $this->assertSame($countAfterFirst, Cave::count());
        $this->assertDatabaseCount('suggested_edits', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_a_failed_overpass_request(): void
    {
        Http::fake(['*' => Http::response('', 504)]);

        $this->artisan('sync:osm-caves')->assertExitCode(1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_retries_and_falls_back_to_another_overpass_endpoint(): void
    {
        Http::fake([
            self::OVERPASS_PATTERN => Http::sequence()
                ->push('<html>runtime error: open64: Dispatcher_Client</html>', 200)
                ->push('', 429),
            '*overpass.private.coffee*' => Http::response($this->mockOverpass(), 200),
        ]);

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Alum Pot']);
        Http::assertSentCount(3);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function an_overpass_timeout_remark_is_a_failure_not_an_empty_result(): void
    {
        Http::fake(['*' => Http::response([
            'elements' => [],
            'remark' => 'runtime error: Query timed out in "query" at line 3 after 181 seconds.',
        ], 200)]);

        $this->artisan('sync:osm-caves')->assertExitCode(1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_an_empty_response_gracefully(): void
    {
        Http::fake([self::OVERPASS_PATTERN => Http::response(['elements' => []], 200)]);

        $this->artisan('sync:osm-caves')->assertExitCode(0);
        $this->assertDatabaseCount('caves', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_leaves_pending_user_edits_on_a_linked_cave_alone(): void
    {
        $this->fakeDefaultHttp();

        $system = CaveSystem::factory()->create(['name' => 'Alum Pot', 'slug' => 'alum-pot']);
        $cave = Cave::factory()->create([
            'name' => 'Alum Pot',
            'cave_system_id' => $system->id,
            'registry' => 'cncc',
            'registry_id' => 'alum-pot',
            'location_lat' => 54.17000,
            'location_lng' => -2.34000,
        ]);

        $user = User::factory()->create();
        $userEdit = SuggestedEdit::create([
            'user_id' => $user->id,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => ['description' => 'Old text'],
            'suggested_data' => ['description' => 'A community-written description'],
            'status' => 'pending',
        ]);

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        $userEdit->refresh();
        $this->assertSame('pending', $userEdit->status);
        $this->assertSame(['description' => 'A community-written description'], $userEdit->suggested_data);
        $this->assertSame(1, SuggestedEdit::count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_touch_the_system_of_a_linked_cave(): void
    {
        $this->fakeDefaultHttp();

        $system = CaveSystem::factory()->create(['name' => 'Alum Pot', 'slug' => 'alum-pot', 'references' => '- [CNCC](https://cncc.org.uk)']);
        Cave::factory()->create([
            'name' => 'Alum Pot',
            'cave_system_id' => $system->id,
            'registry' => 'cncc',
            'registry_id' => 'alum-pot',
            'location_lat' => 54.17545,
            'location_lng' => -2.34649,
        ]);

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        $this->assertSame('- [CNCC](https://cncc.org.uk)', $system->fresh()->references);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_scopes_the_query_to_one_region(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves --region=devon')->assertExitCode(0);

        // Devon box: south 50.25, west -4.55, north 51.05, east -3.4.
        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), '(50.25,-4.55,51.05,-3.4)'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_scopes_the_query_to_a_bounding_box(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:osm-caves --bbox="54.1,-2.5,54.3,-2.2"')->assertExitCode(0);

        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), '(54.1,-2.5,54.3,-2.2)'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_an_unknown_region_or_a_malformed_bbox(): void
    {
        Http::fake();

        $this->artisan('sync:osm-caves --region=Atlantis')->assertExitCode(1);
        $this->artisan('sync:osm-caves --bbox="54.3,-2.5,54.1,-2.2"')->assertExitCode(1); // south > north
        $this->artisan('sync:osm-caves --bbox="nonsense"')->assertExitCode(1);
        $this->artisan('sync:osm-caves --region=Devon --bbox="54.1,-2.5,54.3,-2.2"')->assertExitCode(1);

        Http::assertNothingSent();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function same_named_caves_far_apart_are_both_imported_but_nearby_entrances_are_one_cave(): void
    {
        Http::fake(['*' => Http::response(['elements' => [
            ['type' => 'node', 'id' => 2001, 'lat' => 54.20, 'lon' => -2.30, 'tags' => ['name' => 'Bone Cave']],   // Yorkshire
            ['type' => 'node', 'id' => 2002, 'lat' => 54.2005, 'lon' => -2.3005, 'tags' => ['name' => 'Bone Cave']], // second entrance, ~70m away
            ['type' => 'node', 'id' => 2003, 'lat' => 58.10, 'lon' => -4.90, 'tags' => ['name' => 'Bone Cave']],   // Assynt
        ]], 200)]);

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        $this->assertEqualsCanonicalizing(['2001', '2003'], Cave::where('name', 'Bone Cave')->pluck('osm_node_id')->all());
        $this->assertSame(2, CaveSystem::where('name', 'Bone Cave')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tags_caves_in_northern_ireland(): void
    {
        Http::fake(['*' => Http::response(['elements' => [
            ['type' => 'node', 'id' => 3001, 'lat' => 54.2587, 'lon' => -7.8103, 'tags' => ['name' => 'Marble Arch Caves']],
        ]], 200)]);

        $this->artisan('sync:osm-caves --region="Northern Ireland"')->assertExitCode(0);

        $cave = Cave::where('name', 'Marble Arch Caves')->firstOrFail();
        $this->assertContains('Northern Ireland', $cave->tags->pluck('tag')->all());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_suggest_replacing_a_curated_description_with_boilerplate(): void
    {
        $this->fakeDefaultHttp();

        $richDescription = 'A rich, curated description of the pot and its pitches.';
        $system = CaveSystem::factory()->create(['name' => 'Alum Pot', 'slug' => 'alum-pot']);
        $cave = Cave::factory()->create([
            'name' => 'Alum Pot',
            'cave_system_id' => $system->id,
            'registry' => 'cncc',
            'registry_id' => 'alum-pot',
            'description' => $richDescription,
            'location_name' => 'Yorkshire Dales',
            'location_country' => 'United Kingdom',
            'location_lat' => 54.1754540,
            'location_lng' => -2.3464968,
        ]);

        $this->artisan('sync:osm-caves')->assertExitCode(0);

        // Same coordinates and existing curated text: nothing to propose.
        $this->assertDatabaseMissing('suggested_edits', [
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
        ]);
        $cave->refresh();
        $this->assertSame($richDescription, $cave->description);
    }
}
