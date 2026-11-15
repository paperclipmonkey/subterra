<?php

declare(strict_types=1);

namespace Tests\Feature\Osm;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use App\Models\Trip;
use App\Models\User;
use App\Services\CaveSystemMergeService;
use App\Services\Osm\OsmCaveImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * OpenStreetMap nodes for caves we already hold under another spelling, and
 * merging the duplicates such nodes made before.
 */
class OsmDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tag::firstOrCreate(['tag' => 'Mendip', 'category' => 'region'], ['type' => 'cave']);
    }

    /** @return array{name: string, osm_id: string, lat: float, lng: float, alt: float|null, tags: array<string, string>} */
    private function node(string $id, string $name, float $lat, float $lng): array
    {
        return ['name' => $name, 'osm_id' => $id, 'lat' => $lat, 'lng' => $lng, 'alt' => null, 'tags' => ['name' => $name]];
    }

    private function importer(): OsmCaveImporter
    {
        return app(OsmCaveImporter::class);
    }

    #[Test]
    public function a_node_spelt_differently_beside_a_cave_we_hold_links_it(): void
    {
        $thrupe = Cave::factory()->create(['name' => 'Thrupe Lane Swallet', 'registry' => 'mcra', 'location_lat' => 51.2380, 'location_lng' => -2.5655]);

        $result = $this->importer()->import($this->node('901', 'Thrupe Lane Swallett', 51.23805, -2.56555));   // ~6 m away

        $this->assertSame('linked', $result['action']);
        $this->assertSame($thrupe->id, $result['cave']->id);
        $this->assertSame('901', $thrupe->fresh()->osm_node_id);
        $this->assertSame(1, Cave::count());
    }

    #[Test]
    public function a_similar_name_further_off_is_another_cave(): void
    {
        Cave::factory()->create(['name' => 'Sunset Cave', 'registry' => 'mcra', 'location_lat' => 51.2000, 'location_lng' => -2.6000]);

        $result = $this->importer()->import($this->node('902', 'Sunset Hole', 51.2030, -2.6000));   // ~330 m away

        $this->assertSame('created', $result['action']);
        $this->assertSame(2, Cave::count());
    }

    #[Test]
    public function a_node_for_one_way_in_becomes_another_entrance_of_the_cave(): void
    {
        $yordas = Cave::factory()->create(['name' => 'Yordas Pot', 'registry' => 'mcra', 'location_lat' => 51.2500, 'location_lng' => -2.5000]);
        $systems = CaveSystem::count();

        $result = $this->importer()->import($this->node('903', 'Yordas Pot (Top Entrance)', 51.25005, -2.50005));

        $this->assertSame('created', $result['action']);
        $this->assertSame($yordas->cave_system_id, $result['cave']->cave_system_id);
        $this->assertSame($systems, CaveSystem::count());

        $row = collect($this->importer()->describe([$this->node('904', 'Yordas Pot (Middle Entrance)', 51.25010, -2.50010)]))->first();
        $this->assertSame('entrance', $row['status']);
    }

    #[Test]
    public function a_duplicate_merged_away_is_not_imported_again(): void
    {
        $kept = Cave::factory()->create(['name' => "Swildon's Hole", 'registry' => 'mcra', 'location_lat' => 51.2370, 'location_lng' => -2.6560]);
        $dup = $this->importer()->import($this->node('905', "Swildon's Hole Cavern", 51.2600, -2.6560))['cave'];   // far enough apart to be created

        $admin = User::factory()->dataAdmin()->create();
        $this->actingAs($admin)->postJson("/api/admin/caves/{$kept->slug}/merge", ['source_id' => $dup->id])->assertOk();

        $this->assertSoftDeleted($dup);
        $this->assertSame('905', $kept->fresh()->osm_node_id);
        $again = $this->importer()->import($this->node('905', "Swildon's Hole Cavern", 51.2600, -2.6560));
        $this->assertSame('unchanged', $again['action']);
        $this->assertSame(1, Cave::count());
    }

    #[Test]
    public function merging_a_cave_moves_its_trips_and_takes_its_empty_system(): void
    {
        $kept = Cave::factory()->create(['name' => 'G.B. Cave']);
        $dup = Cave::factory()->create(['name' => 'GB cave']);
        $dupSystem = $dup->cave_system_id;
        $trip = Trip::factory()->create(['cave_system_id' => $dupSystem, 'entrance_cave_id' => $dup->id, 'exit_cave_id' => $dup->id]);
        $admin = User::factory()->dataAdmin()->create();

        $this->actingAs($admin)->getJson("/api/admin/caves/{$kept->slug}/merge-preview?source_id={$dup->id}")
            ->assertOk()
            ->assertJsonPath('moves.trips', 1)
            ->assertJsonPath('moves.system_merged', true);

        $this->postJson("/api/admin/caves/{$kept->slug}/merge", ['source_id' => $dup->id])->assertOk();

        $trip->refresh();
        $this->assertSame($kept->id, $trip->entrance_cave_id);
        $this->assertSame($kept->id, $trip->exit_cave_id);
        $this->assertSame($kept->cave_system_id, $trip->cave_system_id);
        $this->assertNull(CaveSystem::find($dupSystem));
        $this->assertSame($kept->cave_system_id, Cave::withTrashed()->find($dup->id)->cave_system_id);
    }

    #[Test]
    public function only_data_and_platform_admins_merge_caves(): void
    {
        $kept = Cave::factory()->create();
        $dup = Cave::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/admin/caves/{$kept->slug}/merge", ['source_id' => $dup->id])
            ->assertForbidden();
        $this->actingAs(User::factory()->dataAdmin()->create())
            ->postJson("/api/admin/caves/{$kept->slug}/merge", ['source_id' => $kept->id])
            ->assertStatus(422);
        $this->assertNotSoftDeleted($dup);
    }

    #[Test]
    public function merging_systems_takes_their_deleted_caves_along(): void
    {
        $into = CaveSystem::factory()->create();
        $from = CaveSystem::factory()->create();
        $gone = Cave::factory()->create(['cave_system_id' => $from->id]);
        $gone->delete();

        app(CaveSystemMergeService::class)->merge($into, $from);

        $this->assertNull(CaveSystem::find($from->id));
        $this->assertSame($into->id, Cave::withTrashed()->find($gone->id)->cave_system_id);
    }
}
