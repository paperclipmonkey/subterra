<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use App\Support\CaveName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncGsgCavesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'registry.gsg.org.uk/sr/googleEarth/placemarks.php?query=Caves100' => Http::response($this->getMockKml(), 200),
            'registry.gsg.org.uk/sr/googleEarth/placemarks.php?query=Caves' => Http::response($this->getMockKmlEmpty(), 200),
            'registry.gsg.org.uk/sr/sitedetails.php?id=55' => Http::response($this->getMockSiteDetails55(), 200),
            'registry.gsg.org.uk/sr/sitedetails.php?id=56' => Http::response($this->getMockSiteDetails56(), 200),
        ]);

        Tag::firstOrCreate(['tag' => 'Cave', 'category' => 'type'], ['type' => 'cave']);
        Tag::firstOrCreate(['tag' => 'Scotland', 'category' => 'region'], ['type' => 'cave']);
    }

    // -----------------------------------------------------------------------
    // Mock fixtures
    // -----------------------------------------------------------------------

    private function getMockKml(): string
    {
        return <<<'KML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
 <Document>
  <Placemark>
   <name>Claonaite</name>
   <description><![CDATA[<p>One of Scotland's longest cave systems.</p><p><a href="https://registry.gsg.org.uk/sr/sitedetails.php?id=55">Full Site Details</a></p><p><small>Database content Copyright 2026 Grampian Speleological Group</small></p>]]></description>
   <Point><coordinates>-5.10,56.72</coordinates></Point>
  </Placemark>
  <Placemark>
   <name>Uamh an Claonaite</name>
   <description><![CDATA[<p>A stream cave in Argyll.</p><p><a href="https://registry.gsg.org.uk/sr/sitedetails.php?id=56">Full Site Details</a></p><p><small>Database content Copyright 2026 Grampian Speleological Group</small></p>]]></description>
   <Point><coordinates>-5.11,56.73</coordinates></Point>
  </Placemark>
 </Document>
</kml>
KML;
    }

    private function getMockKmlEmpty(): string
    {
        return <<<'KML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2"><Document></Document></kml>
KML;
    }

    private function getMockSiteDetails55(): string
    {
        // GSG uses "Vert. Range:" rather than "Depth:"
        return <<<'HTML'
<html><body>
<h1>Claonaite</h1>
<p><strong>Argyll, Scotland.</strong></p>
<table class='rowhover'>
<tr><td>Length:</td><td>1800 m</td></tr>
<tr><td>Vert. Range:</td><td>95 m</td></tr>
<tr><td>Altitude:</td><td>210 m</td></tr>
</table>
</body></html>
HTML;
    }

    private function getMockSiteDetails56(): string
    {
        return <<<'HTML'
<html><body>
<h1>Uamh an Claonaite</h1>
<p><strong>Argyll, Scotland.</strong></p>
<table class='rowhover'>
<tr><td>Length:</td><td>650 m</td></tr>
<tr><td>Vert. Range:</td><td>30 m</td></tr>
<tr><td>Altitude:</td><td>190 m</td></tr>
</table>
</body></html>
HTML;
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_new_gsg_caves(): void
    {
        $this->artisan('sync:gsg-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Claonaite']);
        $this->assertDatabaseHas('caves', ['name' => 'Uamh an Claonaite']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_registry_and_registry_id_on_new_caves(): void
    {
        $this->artisan('sync:gsg-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Claonaite')->firstOrFail();
        $this->assertEquals('gsg', $cave->registry);
        $this->assertEquals('55', $cave->registry_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_cave_and_scotland_tags(): void
    {
        $this->artisan('sync:gsg-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Claonaite')->firstOrFail();
        $tagNames = $cave->tags->pluck('tag')->toArray();

        $this->assertContains('Cave', $tagNames);
        $this->assertContains('Scotland', $tagNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_vert_range_field_for_depth(): void
    {
        $this->artisan('sync:gsg-caves')
            ->assertExitCode(0);

        $system = CaveSystem::where('name', 'Claonaite')->firstOrFail();
        $this->assertEquals(95, (int) $system->vertical_range);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_scotland_slug_prefix(): void
    {
        $this->artisan('sync:gsg-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', [
            'name' => 'Claonaite',
            'slug' => 'scotland_claonaite',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_appends_gsg_registry_link_to_description(): void
    {
        $this->artisan('sync:gsg-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Claonaite')->firstOrFail();
        $this->assertStringContainsString(
            '[GSG Registry](https://registry.gsg.org.uk/sr/sitedetails.php?id=55)',
            $cave->description
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_respects_the_blocklist(): void
    {
        $this->artisan('sync:gsg-caves --blocklist="Claonaite"')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('caves', ['name' => 'Claonaite']);
        $this->assertDatabaseHas('caves', ['name' => 'Uamh an Claonaite']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_min_length_filter(): void
    {
        $this->artisan('sync:gsg-caves --min-length=1000')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Claonaite']);
        $this->assertDatabaseMissing('caves', ['name' => 'Uamh an Claonaite']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_dry_run_does_not_persist_data(): void
    {
        $this->artisan('sync:gsg-caves --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('caves', ['name' => 'Claonaite']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_reuses_an_existing_differently_cased_cave_instead_of_duplicating(): void
    {
        // A copy of the cave already exists from another registry, spelled with
        // different capitalisation ("An" vs "an") and with no GSG registry id.
        $system = CaveSystem::create(['name' => 'Uamh An Claonaite', 'slug' => 'uamh-an-claonaite-existing', 'length' => 0, 'vertical_range' => 0]);
        $existing = Cave::create([
            'name' => 'Uamh An Claonaite',
            'slug' => 'cncc_uamh-an-claonaite',
            'cave_system_id' => $system->id,
            'registry' => 'cncc',
            'registry_id' => 'uamh-an-claonaite',
            'location_name' => 'Assynt',
            'location_country' => 'United Kingdom',
            'location_lat' => 56.73,
            'location_lng' => -5.11,
        ]);

        $this->artisan('sync:gsg-caves')->assertExitCode(0);

        // Still exactly one cave for this place, and it kept its original record.
        $this->assertSame(1, CaveName::match(Cave::query(), 'Uamh an Claonaite')->count());
        $this->assertDatabaseMissing('caves', ['name' => 'Uamh an Claonaite']);
        $this->assertTrue(Cave::whereKey($existing->id)->exists());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_reuses_an_existing_differently_cased_system_instead_of_duplicating(): void
    {
        CaveSystem::create(['name' => 'Uamh An Claonaite', 'slug' => 'uamh-an-claonaite-existing', 'length' => 0, 'vertical_range' => 0]);

        $this->artisan('sync:gsg-caves')->assertExitCode(0);

        $this->assertSame(1, CaveName::match(CaveSystem::query(), 'Uamh an Claonaite')->count());
    }
}
