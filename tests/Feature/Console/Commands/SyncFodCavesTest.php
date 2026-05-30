<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncFodCavesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'www.fodccag.org.uk/registry/googleEarth/placemarks.php?query=Caves100' => Http::response($this->getMockKml(), 200),
            'www.fodccag.org.uk/registry/googleEarth/placemarks.php?query=Caves' => Http::response($this->getMockKmlEmpty(), 200),
            'www.fodccag.org.uk/registry/sitedetails.php?id=42' => Http::response($this->getMockSiteDetails42(), 200),
            'www.fodccag.org.uk/registry/sitedetails.php?id=43' => Http::response($this->getMockSiteDetails43(), 200),
        ]);

        Tag::firstOrCreate(['tag' => 'Cave', 'category' => 'type'], ['type' => 'cave']);
        Tag::firstOrCreate(['tag' => 'Forest of Dean', 'category' => 'region'], ['type' => 'cave']);
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
   <name>Otter Hole</name>
   <description><![CDATA[<p>A beautiful streamway cave requiring tidal timing.</p><p><a href="https://www.fodccag.org.uk/registry/sitedetails.php?id=42">Full Site Details</a></p><p><small>Database content Copyright 2026 Forest of Dean Cave Conservation and Access Group</small></p>]]></description>
   <Point><coordinates>-2.66,51.78</coordinates></Point>
  </Placemark>
  <Placemark>
   <name>Slaughter Stream Cave</name>
   <description><![CDATA[<p>A long streamway cave in the Forest of Dean.</p><p><a href="https://www.fodccag.org.uk/registry/sitedetails.php?id=43">Full Site Details</a></p><p><small>Database content Copyright 2026 Forest of Dean Cave Conservation and Access Group</small></p>]]></description>
   <Point><coordinates>-2.55,51.82</coordinates></Point>
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

    private function getMockSiteDetails42(): string
    {
        return <<<'HTML'
<html><body>
<h1>Otter Hole</h1>
<p><strong>Chepstow, Wye Valley.</strong></p>
<table class='rowhover'>
<tr><td>Length:</td><td>3550 m</td></tr>
<tr><td>Depth:</td><td>68 m</td></tr>
<tr><td>Altitude:</td><td>5 m</td></tr>
</table>
</body></html>
HTML;
    }

    private function getMockSiteDetails43(): string
    {
        return <<<'HTML'
<html><body>
<h1>Slaughter Stream Cave</h1>
<p><strong>Staunton, Forest of Dean.</strong></p>
<table class='rowhover'>
<tr><td>Length:</td><td>2800 m</td></tr>
<tr><td>Depth:</td><td>45 m</td></tr>
<tr><td>Altitude:</td><td>100 m</td></tr>
</table>
</body></html>
HTML;
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_new_fod_caves(): void
    {
        $this->artisan('sync:fod-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Otter Hole']);
        $this->assertDatabaseHas('caves', ['name' => 'Slaughter Stream Cave']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_registry_and_registry_id_on_new_caves(): void
    {
        $this->artisan('sync:fod-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Otter Hole')->firstOrFail();
        $this->assertEquals('fod', $cave->registry);
        $this->assertEquals('42', $cave->registry_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_cave_and_forest_of_dean_tags(): void
    {
        $this->artisan('sync:fod-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Otter Hole')->firstOrFail();
        $tagNames = $cave->tags->pluck('tag')->toArray();

        $this->assertContains('Cave', $tagNames);
        $this->assertContains('Forest of Dean', $tagNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_fod_slug_prefix(): void
    {
        $this->artisan('sync:fod-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', [
            'name' => 'Otter Hole',
            'slug' => 'fod_otter-hole',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_appends_fod_registry_link_to_description(): void
    {
        $this->artisan('sync:fod-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Otter Hole')->firstOrFail();
        $this->assertStringContainsString(
            '[FoD Registry](http://www.fodccag.org.uk/registry/sitedetails.php?id=42)',
            $cave->description
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_respects_the_blocklist(): void
    {
        $this->artisan('sync:fod-caves --blocklist="Otter Hole"')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('caves', ['name' => 'Otter Hole']);
        $this->assertDatabaseHas('caves', ['name' => 'Slaughter Stream Cave']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_min_length_filter(): void
    {
        $this->artisan('sync:fod-caves --min-length=3000')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Otter Hole']);
        $this->assertDatabaseMissing('caves', ['name' => 'Slaughter Stream Cave']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_cave_system_with_fod_reference(): void
    {
        $this->artisan('sync:fod-caves')
            ->assertExitCode(0);

        $system = CaveSystem::where('name', 'Otter Hole')->first();
        $this->assertNotNull($system);
        $this->assertStringContainsString(
            '[FoD Registry](http://www.fodccag.org.uk/registry/sitedetails.php?id=42)',
            $system->references
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_dry_run_does_not_persist_data(): void
    {
        $this->artisan('sync:fod-caves --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('caves', ['name' => 'Otter Hole']);
    }
}
