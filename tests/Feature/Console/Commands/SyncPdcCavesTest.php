<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\SuggestedEdit;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncPdcCavesTest extends TestCase
{
    use RefreshDatabase;

    private const INDEX_URL_PATTERN = '*peakdistrictcaving.info/home/the-caves';

    private const CASTLETON_URL_PATTERN = '*peakdistrictcaving.info/home/the-caves/castleton';

    private const GIANTS_HOLE_URL_PATTERN = '*peakdistrictcaving.info/home/the-caves/castleton/giants-hole';

    private const ELDON_HOLE_URL_PATTERN = '*peakdistrictcaving.info/home/the-caves/castleton/eldon-hole';

    private const BLOCKED_CAVE_URL_PATTERN = '*peakdistrictcaving.info/home/the-caves/castleton/blocked-cave';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Tag::firstOrCreate(['tag' => 'Cave', 'category' => 'type'], ['type' => 'cave']);
        Tag::firstOrCreate(['tag' => 'Peak District', 'category' => 'region'], ['type' => 'cave']);
    }

    /** Set up the default Http fakes for a normal sync run. */
    private function fakeDefaultHttp(): void
    {
        Http::fake([
            self::GIANTS_HOLE_URL_PATTERN => Http::response($this->getMockDetailGiantsHole(), 200),
            self::ELDON_HOLE_URL_PATTERN => Http::response($this->getMockDetailEldonHole(), 200),
            self::BLOCKED_CAVE_URL_PATTERN => Http::response($this->getMockDetailGeneric(), 200),
            self::CASTLETON_URL_PATTERN => Http::response($this->getMockRegionPage(), 200),
            self::INDEX_URL_PATTERN => Http::response($this->getMockIndex(), 200),
        ]);
    }

    // -----------------------------------------------------------------------
    // Mock HTML fixtures
    // -----------------------------------------------------------------------

    private function getMockIndex(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<body>
<nav class="menu">
    <a href="/home/the-caves/search">Advanced search</a>
    <a href="/home/the-caves/hydrology">Hydrology</a>
    <a href="/home/the-caves/topos">Rigging topos</a>
    <a href="/home/the-caves/surveys">Surveys</a>
    <a href="/home/the-caves/guides">Guides</a>
    <a href="/home/the-caves/audits">Conservation audits</a>
</nav>
<nav>
    <a href="/home/the-caves/castleton">Castleton</a>
</nav>
</body>
</html>
HTML;
    }

    private function getMockRegionPage(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<body>
<table id="entrance-table" class="tbl--sort table-scroll">
    <thead>
        <tr>
            <th>Entrance</th>
            <th class="hide col-length">Length</th>
            <th class="hide col-depth">Depth</th>
            <th class="no-sort col-access">Access</th>
            <th class="no-sort col-resources hide">Documents</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><a href="/home/the-caves/castleton/giants-hole" property="karstlink:relatedToUndergroundCavity">Giants Hole</a></td>
            <td class="dim-m hide">2298</td>
            <td class="dim-m hide">140</td>
            <td><i class="icons">£</i></td>
            <td class="hide"></td>
        </tr>
        <tr>
            <td><a href="/home/the-caves/castleton/eldon-hole" property="karstlink:relatedToUndergroundCavity">Eldon Hole</a></td>
            <td class="dim-m hide">200</td>
            <td class="dim-m hide">85</td>
            <td></td>
            <td class="hide"></td>
        </tr>
        <tr>
            <td><a href="/home/the-caves/castleton/blocked-cave" property="karstlink:relatedToUndergroundCavity">Blocked Cave</a></td>
            <td class="dim-m hide"></td>
            <td class="dim-m hide"></td>
            <td></td>
            <td class="hide"></td>
        </tr>
    </tbody>
</table>
</body>
</html>
HTML;
    }

    private function getMockDetailGiantsHole(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<body>
<section class="sidebar entrance-summary top-1">
    <table>
        <tr><td>Length</td><td>2298 m</td></tr>
        <tr><td>Depth</td><td>140 m</td></tr>
        <tr class="tr-location"><td>Location</td>
            <td><span property="geo:lat">53.3409</span> <span property="geo:long">-1.8221</span></td>
        </tr>
    </table>
</section>
<section class="sidebar no-print">
    <section class="md access_description" ><h3>Access</h3><p>Trespass fee of &pound;3 to be put in the box at the car park.</p>
    </section>
</section>
</body>
</html>
HTML;
    }

    private function getMockDetailEldonHole(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<body>
<section class="sidebar entrance-summary top-1">
    <table>
        <tr><td>Length</td><td>200 m</td></tr>
        <tr><td>Depth</td><td>85 m</td></tr>
        <tr class="tr-location"><td>Location</td>
            <td><span property="geo:lat">53.3291</span> <span property="geo:long">-1.8234</span></td>
        </tr>
    </table>
</section>
<section class="sidebar no-print">
    <section class="md access_description" ><h3>Access</h3><p>Open access. No permit required.</p>
    </section>
</section>
</body>
</html>
HTML;
    }

    private function getMockDetailGeneric(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<body>
<section class="sidebar entrance-summary top-1">
    <table>
        <tr><td>Length</td><td>-</td></tr>
        <tr><td>Depth</td><td>-</td></tr>
        <tr><td><span property="geo:lat">53.3400</span> <span property="geo:long">-1.8200</span></td></tr>
    </table>
</section>
</body>
</html>
HTML;
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_new_pdc_caves(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Giants Hole']);
        $this->assertDatabaseHas('caves', ['name' => 'Eldon Hole']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_registry_and_registry_id_on_new_caves(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Giants Hole')->firstOrFail();
        $this->assertEquals('pdc', $cave->registry);
        $this->assertEquals('castleton/giants-hole', $cave->registry_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_location_name_from_region(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', [
            'name' => 'Giants Hole',
            'location_name' => 'Castleton',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_length_and_vertical_range_on_cave_system(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        $system = CaveSystem::where('name', 'Giants Hole')->firstOrFail();
        $this->assertEquals(2298, $system->length);
        $this->assertEquals(140, $system->vertical_range);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_defaults_cave_system_length_and_vertical_range_to_zero_when_missing(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        // Blocked Cave has no length or depth in the region page — defaults to 0
        $system = CaveSystem::where('name', 'Blocked Cave')->firstOrFail();
        $this->assertEquals(0, $system->length);
        $this->assertEquals(0, $system->vertical_range);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_coordinates_from_detail_page(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Giants Hole')->firstOrFail();
        $this->assertEqualsWithDelta(53.3409, $cave->location_lat, 0.0001);
        $this->assertEqualsWithDelta(-1.8221, $cave->location_lng, 0.0001);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_pdc_slug_prefix(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', [
            'name' => 'Giants Hole',
            'slug' => 'pdc_castleton_giants-hole',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_cave_and_peak_district_tags(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Giants Hole')->firstOrFail();
        $tagNames = $cave->tags->pluck('tag')->toArray();

        $this->assertContains('Cave', $tagNames);
        $this->assertContains('Peak District', $tagNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_a_cave_system_for_each_cave(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('cave_systems', ['name' => 'Giants Hole']);
        $this->assertDatabaseHas('cave_systems', ['name' => 'Eldon Hole']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_pdc_reference_link_to_cave_system(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        $system = CaveSystem::where('name', 'Giants Hole')->firstOrFail();
        $this->assertStringContainsString('peakdistrictcaving.info/home/the-caves/castleton/giants-hole', $system->references);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_pdc_link_to_cave_description_and_access_info(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Giants Hole')->firstOrFail();
        $this->assertStringContainsString('peakdistrictcaving.info/home/the-caves/castleton/giants-hole', $cave->description);
        $this->assertStringContainsString('Giants Hole', $cave->description);
        $this->assertStringContainsString('peakdistrictcaving.info/home/the-caves/castleton/giants-hole', $cave->access_info);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_scraped_access_info_in_access_field(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Giants Hole')->firstOrFail();
        $this->assertStringContainsString('Trespass fee', $cave->access_info);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_respects_the_blocklist(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:pdc-caves --blocklist="Blocked Cave"')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Giants Hole']);
        $this->assertDatabaseMissing('caves', ['name' => 'Blocked Cave']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_import_data_in_dry_run_mode(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:pdc-caves --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseCount('caves', 0);
        $this->assertDatabaseCount('cave_systems', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_suggested_edit_for_existing_cave_with_coordinate_changes(): void
    {
        $this->fakeDefaultHttp();

        $system = CaveSystem::factory()->create(['name' => 'Giants Hole', 'slug' => 'giants-hole']);
        $cave = Cave::factory()->create([
            'name' => 'Giants Hole',
            'cave_system_id' => $system->id,
            'location_lat' => 51.0,
            'location_lng' => -3.0,
        ]);

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        // Cave should not be directly updated
        $this->assertDatabaseHas('caves', [
            'id' => $cave->id,
            'location_lat' => 51.0,
        ]);

        // Suggested edit should be created
        $this->assertDatabaseHas('suggested_edits', [
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'status' => 'pending',
        ]);

        $edit = SuggestedEdit::where('suggestable_id', $cave->id)
            ->where('suggestable_type', Cave::class)
            ->firstOrFail();
        $this->assertNull($edit->user_id);
        $this->assertArrayHasKey('location_lat', $edit->suggested_data);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_create_suggested_edit_when_cave_has_no_changes(): void
    {
        $this->fakeDefaultHttp();

        $pdcLink = '[Peak District Caving page for Giants Hole](https://peakdistrictcaving.info/home/the-caves/castleton/giants-hole)';
        $system = CaveSystem::factory()->create([
            'name' => 'Giants Hole',
            'slug' => 'giants-hole',
            'references' => '- [Peak District Caving page](https://peakdistrictcaving.info/home/the-caves/castleton/giants-hole)',
        ]);
        Cave::factory()->create([
            'name' => 'Giants Hole',
            'cave_system_id' => $system->id,
            'description' => 'For more information see '.$pdcLink.'.',
            'access_info' => "Trespass fee of £3 to be put in the box at the car park.\n\nFor more information see ".$pdcLink.'.',
            'location_name' => 'Castleton',
            'location_lat' => 53.3409,
            'location_lng' => -1.8221,
            'location_country' => 'United Kingdom',

        ]);

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        $this->assertDatabaseCount('suggested_edits', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_an_empty_region_gracefully(): void
    {
        Http::fake([
            self::CASTLETON_URL_PATTERN => Http::response('<html><body><table id="entrance-table"><tbody></tbody></table></body></html>', 200),
            self::INDEX_URL_PATTERN => Http::response($this->getMockIndex(), 200),
        ]);

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        $this->assertDatabaseCount('caves', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_a_failed_index_request(): void
    {
        Http::fake([
            self::INDEX_URL_PATTERN => Http::response('', 503),
        ]);

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_a_failed_region_request_gracefully(): void
    {
        Http::fake([
            self::CASTLETON_URL_PATTERN => Http::response('', 503),
            self::INDEX_URL_PATTERN => Http::response($this->getMockIndex(), 200),
        ]);

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        $this->assertDatabaseCount('caves', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_a_missing_detail_page_gracefully(): void
    {
        Http::fake([
            self::GIANTS_HOLE_URL_PATTERN => Http::response('', 404),
            self::ELDON_HOLE_URL_PATTERN => Http::response($this->getMockDetailEldonHole(), 200),
            self::BLOCKED_CAVE_URL_PATTERN => Http::response($this->getMockDetailGeneric(), 200),
            self::CASTLETON_URL_PATTERN => Http::response($this->getMockRegionPage(), 200),
            self::INDEX_URL_PATTERN => Http::response($this->getMockIndex(), 200),
        ]);

        $this->artisan('sync:pdc-caves')
            ->assertExitCode(0);

        // A cave with no extractable GPS coordinates should be skipped, not
        // imported at a useless 0,0 location — and no orphan system left behind.
        $this->assertDatabaseMissing('caves', ['name' => 'Giants Hole']);
        $this->assertDatabaseMissing('cave_systems', ['name' => 'Giants Hole']);
    }
}
