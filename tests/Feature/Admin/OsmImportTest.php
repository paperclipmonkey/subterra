<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Curated OpenStreetMap import: preview a region, import only chosen caves.
 */
class OsmImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        Tag::firstOrCreate(['tag' => 'Devon', 'category' => 'region'], ['type' => 'cave']);
    }

    /** @return array<string, mixed> */
    private function devonPayload(): array
    {
        return ['elements' => [
            ['type' => 'node', 'id' => 501, 'lat' => 50.4886, 'lon' => -3.7536, 'tags' => ['natural' => 'cave_entrance', 'name' => 'Pridhamsleigh Cavern', 'access' => 'permissive']],
            ['type' => 'node', 'id' => 502, 'lat' => 50.4720, 'lon' => -3.7700, 'tags' => ['natural' => 'cave_entrance', 'name' => "Baker's Pit", 'website' => 'https://example.org/bakers']],
            ['type' => 'node', 'id' => 503, 'lat' => 50.3700, 'lon' => -4.0400, 'tags' => ['natural' => 'cave_entrance', 'name' => 'Kitley Show Cave', 'wikipedia' => 'en:Kitley Caves']],
            ['type' => 'node', 'id' => 504, 'lat' => 50.4000, 'lon' => -3.9000, 'tags' => ['natural' => 'cave_entrance']], // unnamed: never offered
        ]];
    }

    private function dataAdmin(): User
    {
        return User::factory()->dataAdmin()->create();
    }

    #[Test]
    public function only_platform_and_data_admins_can_use_it(): void
    {
        Http::fake(['*' => Http::response($this->devonPayload())]);

        $this->actingAs(User::factory()->dutyOfficer()->create())
            ->getJson('/api/admin/osm/candidates?region=Devon')
            ->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->postJson('/api/admin/osm/import', ['node_ids' => ['501']])
            ->assertForbidden();

        $this->actingAs($this->dataAdmin())->getJson('/api/admin/osm/candidates?region=Devon')->assertOk();
    }

    #[Test]
    public function candidates_show_what_an_import_would_do(): void
    {
        Http::fake(['*' => Http::response($this->devonPayload())]);

        // Baker's Pit already held from a registry; Kitley already linked.
        Cave::factory()->create(['name' => "Baker's Pit", 'registry' => 'mcra', 'location_lat' => 50.4721, 'location_lng' => -3.7701]);
        $kitley = Cave::factory()->create(['name' => 'Kitley Show Cave', 'location_lat' => 50.37, 'location_lng' => -4.04]);
        $kitley->forceFill(['osm_node_id' => '503'])->save();

        $response = $this->actingAs($this->dataAdmin())
            ->getJson('/api/admin/osm/candidates?region=Devon')
            ->assertOk()
            ->assertJsonPath('attribution', 'Cave data © OpenStreetMap contributors, available under the Open Database Licence (ODbL).');

        $rows = collect($response->json('data'))->keyBy('name');
        $this->assertCount(3, $rows); // unnamed node not offered
        $this->assertSame('new', $rows['Pridhamsleigh Cavern']['status']);
        $this->assertSame('permissive', $rows['Pridhamsleigh Cavern']['access']);
        $this->assertSame('https://www.openstreetmap.org/node/501', $rows['Pridhamsleigh Cavern']['osm_url']);
        $this->assertSame('existing', $rows["Baker's Pit"]['status']);
        $this->assertSame('linked', $rows['Kitley Show Cave']['status']);
    }

    #[Test]
    public function candidates_validate_the_region_and_report_overpass_outages(): void
    {
        $this->actingAs($this->dataAdmin())->getJson('/api/admin/osm/candidates?region=Atlantis')->assertStatus(422);

        Http::fake(['*' => Http::response('', 504)]);
        $this->getJson('/api/admin/osm/candidates?region=Devon')->assertStatus(502);
    }

    #[Test]
    public function importing_creates_only_the_chosen_caves_with_attribution(): void
    {
        Http::fake(['*' => Http::response(['elements' => [$this->devonPayload()['elements'][0]]])]);

        $response = $this->actingAs($this->dataAdmin())
            ->postJson('/api/admin/osm/import', ['node_ids' => ['501']])
            ->assertOk()
            ->assertJsonPath('data.0.action', 'created');

        // The chosen node was re-fetched from OSM by id, not taken from the client.
        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'node(id:501)'));

        $cave = Cave::where('name', 'Pridhamsleigh Cavern')->firstOrFail();
        $this->assertSame('osm', $cave->registry);
        $this->assertSame('501', $cave->osm_node_id);
        $this->assertStringContainsString('openstreetmap.org/node/501', $cave->description);
        $this->assertStringContainsString('ODbL', $cave->description);
        $this->assertContains('Devon', $cave->tags->pluck('tag')->all());
        $this->assertSame(1, Cave::count());
        $this->assertSame($cave->slug, $response->json('data.0.cave.slug'));
    }

    #[Test]
    public function importing_a_cave_we_already_hold_only_links_it(): void
    {
        Http::fake(['*' => Http::response(['elements' => [$this->devonPayload()['elements'][1]]])]);
        $system = CaveSystem::factory()->create(['references' => null]);
        $existing = Cave::factory()->create([
            'name' => "Baker's Pit",
            'cave_system_id' => $system->id,
            'registry' => 'mcra',
            'description' => 'Registry description',
            'location_lat' => 50.4730,
            'location_lng' => -3.7710,
        ]);

        $this->actingAs($this->dataAdmin())
            ->postJson('/api/admin/osm/import', ['node_ids' => ['502']])
            ->assertOk()
            ->assertJsonPath('data.0.action', 'linked');

        $existing->refresh();
        $this->assertSame('502', $existing->osm_node_id);
        $this->assertSame('mcra', $existing->registry);
        $this->assertSame('Registry description', $existing->description);
        $this->assertEquals(50.4730, (float) $existing->location_lat);
        $this->assertNull($system->fresh()->references);
        $this->assertDatabaseCount('suggested_edits', 0);
        $this->assertSame(1, Cave::count());
    }

    #[Test]
    public function nodes_osm_no_longer_has_are_reported_not_imported(): void
    {
        Http::fake(['*' => Http::response(['elements' => []])]);

        $this->actingAs($this->dataAdmin())
            ->postJson('/api/admin/osm/import', ['node_ids' => ['999']])
            ->assertOk()
            ->assertJsonPath('data.0.action', 'not_found');

        $this->assertSame(0, Cave::count());
    }

    #[Test]
    public function import_input_is_validated(): void
    {
        Http::fake();
        $admin = $this->dataAdmin();

        $this->actingAs($admin)->postJson('/api/admin/osm/import', ['node_ids' => []])->assertStatus(422);
        $this->actingAs($admin)->postJson('/api/admin/osm/import', ['node_ids' => ['1);out;node(1']])->assertStatus(422);
        $this->actingAs($admin)->postJson('/api/admin/osm/import', ['node_ids' => array_map('strval', range(1, 101))])->assertStatus(422);

        Http::assertNothingSent();
    }
}
