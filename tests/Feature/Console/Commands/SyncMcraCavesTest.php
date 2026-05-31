<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\SuggestedEdit;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncMcraCavesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'www.mcra.org.uk/registry/googleEarth/placemarks.php?query=Caves100' => Http::response($this->getMockKmlCaves100(), 200),
            'www.mcra.org.uk/registry/googleEarth/placemarks.php?query=Caves' => Http::response($this->getMockKmlCaves(), 200),
            'www.mcra.org.uk/registry/sitedetails.php?id=97' => Http::response($this->getMockSiteDetails97(), 200),
            'www.mcra.org.uk/registry/sitedetails.php?id=384' => Http::response($this->getMockSiteDetails384(), 200),
            'www.mcra.org.uk/registry/sitedetails.php?id=500' => Http::response($this->getMockSiteDetails500Portland(), 200),
            'www.mcra.org.uk/registry/sitedetails.php?id=999' => Http::response($this->getMockSiteDetailsShort(), 200),
            'www.mcra.org.uk/registry/sitedetails.php?id=123' => Http::response($this->getMockSiteDetailsUtf8(), 200),
        ]);

        Tag::firstOrCreate(['tag' => 'Cave', 'category' => 'type'], ['type' => 'cave']);
        Tag::firstOrCreate(['tag' => 'Mendip', 'category' => 'region'], ['type' => 'cave']);
    }

    // -----------------------------------------------------------------------
    // Mock KML fixtures
    // -----------------------------------------------------------------------

    private function getMockKmlCaves100(): string
    {
        return <<<'KML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
 <Document>
  <Placemark>
   <name>Attborough Swallet</name>
   <description><![CDATA[<p>Cave in Dolomitic Conglomerate and Marl with extensive upper series.</p><p><a href="https://www.mcra.org.uk/registry/sitedetails.php?id=97">Full Site Details</a></p><p><small>Database content Copyright 2026 <a href="https://www.mcra.org.uk">Mendip Cave Registry and Archive</a></small></p>]]></description>
   <Point><coordinates>-2.63059675384921,51.2636921788568</coordinates></Point>
  </Placemark>
  <Placemark>
   <name>Balch Cave</name>
   <description><![CDATA[<p>Once one of Britain's most beautifully decorated caves.</p><p><a href="https://www.mcra.org.uk/registry/sitedetails.php?id=384">Full Site Details</a></p><p><small>Database content Copyright 2026 <a href="https://www.mcra.org.uk">Mendip Cave Registry and Archive</a></small></p>]]></description>
   <Point><coordinates>-2.49186482092332,51.2265554769675</coordinates></Point>
  </Placemark>
  <Placemark>
   <name>Portland Cave</name>
   <description><![CDATA[<p>A coastal cave on Portland.</p><p><a href="https://www.mcra.org.uk/registry/sitedetails.php?id=500">Full Site Details</a></p><p><small>Database content Copyright 2026 <a href="https://www.mcra.org.uk">Mendip Cave Registry and Archive</a></small></p>]]></description>
   <Point><coordinates>-2.454,50.543</coordinates></Point>
  </Placemark>
  <Placemark>
   <name>Sandy Hole Connection</name>
   <description><![CDATA[<p>Connected to Sandy Hole via a very difficult and tight rift providing a long and sporting through trip. Combined length of caves is ≈2,489 m.</p><p><a href="https://www.mcra.org.uk/registry/sitedetails.php?id=123">Full Site Details</a></p><p><small>Database content Copyright 2026 <a href="https://www.mcra.org.uk">Mendip Cave Registry and Archive</a></small></p>]]></description>
   <Point><coordinates>-2.71,51.22</coordinates></Point>
  </Placemark>
 </Document>
</kml>
KML;
    }

    private function getMockKmlCaves(): string
    {
        return <<<'KML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
 <Document>
  <Placemark>
   <name>Short Cave Mendip</name>
   <description><![CDATA[<p>A small cave in the Mendips.</p><p><a href="https://www.mcra.org.uk/registry/sitedetails.php?id=999">Full Site Details</a></p><p><small>Database content Copyright 2026 <a href="https://www.mcra.org.uk">Mendip Cave Registry and Archive</a></small></p>]]></description>
   <Point><coordinates>-2.5,51.3</coordinates></Point>
  </Placemark>
 </Document>
</kml>
KML;
    }

    private function getMockSiteDetails97(): string
    {
        return <<<'HTML'
<html><body>
<h1>Attborough Swallet</h1>
<p><strong>Red Quar, Chewton Mendip.</strong></p>
<table class='rowhover'>
<tr><td>Length:</td><td>244 m</td></tr>
<tr><td>Depth:</td><td>46 m</td></tr>
<tr><td>Altitude:</td><td>253 m</td></tr>
</table>
</body></html>
HTML;
    }

    private function getMockSiteDetails384(): string
    {
        return <<<'HTML'
<html><body>
<h1>Balch Cave</h1>
<p><strong>Fairy Cave Quarry, Stoke St Michael.</strong></p>
<table class='rowhover'>
<tr><td>Length:</td><td>310 m</td></tr>
<tr><td>Depth:</td><td>15 m</td></tr>
<tr><td>Altitude:</td><td>130 m</td></tr>
</table>
</body></html>
HTML;
    }

    private function getMockSiteDetailsShort(): string
    {
        return <<<'HTML'
<html><body>
<h1>Short Cave Mendip</h1>
<p><strong>Near Cheddar.</strong></p>
<table class='rowhover'>
<tr><td>Length:</td><td>50 m</td></tr>
<tr><td>Depth:</td><td>5 m</td></tr>
<tr><td>Altitude:</td><td>80 m</td></tr>
</table>
</body></html>
HTML;
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_new_caves_with_no_min_length_filter(): void
    {
        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Attborough Swallet']);
        $this->assertDatabaseHas('caves', ['name' => 'Balch Cave']);
        $this->assertDatabaseHas('caves', ['name' => 'Short Cave Mendip']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_registry_and_registry_id_on_new_caves(): void
    {
        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Attborough Swallet')->firstOrFail();
        $this->assertEquals('mcra', $cave->registry);
        $this->assertEquals('97', $cave->registry_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_min_length_filter_using_site_details(): void
    {
        $this->artisan('sync:mcra-caves --min-length=200')
            ->assertExitCode(0);

        // Attborough (244m) and Balch Cave (310m) should be imported
        $this->assertDatabaseHas('caves', ['name' => 'Attborough Swallet']);
        $this->assertDatabaseHas('caves', ['name' => 'Balch Cave']);
        // Short Cave Mendip (50m) should be skipped
        $this->assertDatabaseMissing('caves', ['name' => 'Short Cave Mendip']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_respects_the_blocklist(): void
    {
        $this->artisan('sync:mcra-caves --blocklist="Attborough Swallet"')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('caves', ['name' => 'Attborough Swallet']);
        $this->assertDatabaseHas('caves', ['name' => 'Balch Cave']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_cave_and_mendip_tags_to_new_caves(): void
    {
        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Attborough Swallet')->firstOrFail();
        $tagNames = $cave->tags->pluck('tag')->toArray();

        $this->assertContains('Cave', $tagNames);
        $this->assertContains('Mendip', $tagNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_add_curated_tag_to_new_caves(): void
    {
        Tag::firstOrCreate(['tag' => 'Curated', 'category' => 'curated'], ['type' => 'cave']);

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Attborough Swallet')->firstOrFail();
        $tagNames = $cave->tags->pluck('tag')->toArray();

        $this->assertNotContains('Curated', $tagNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_strips_html_from_kml_description(): void
    {
        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Attborough Swallet')->firstOrFail();
        $this->assertStringContainsString('Cave in Dolomitic Conglomerate', $cave->description);
        $this->assertStringNotContainsString('<p>', $cave->description);
        $this->assertStringNotContainsString('Full Site Details', $cave->description);
        $this->assertStringNotContainsString('Database content Copyright', $cave->description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_appends_mcra_registry_link_to_description(): void
    {
        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Attborough Swallet')->firstOrFail();
        $this->assertStringContainsString('[MCRA Registry](https://www.mcra.org.uk/registry/sitedetails.php?id=97)', $cave->description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_suggested_edit_for_existing_cave_with_differences(): void
    {
        $cave = Cave::factory()->create([
            'name' => 'Attborough Swallet',
            'description' => 'Old description',
            'location_lat' => 51.0,
            'location_lng' => -3.0,
        ]);

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        // Cave should NOT be directly updated
        $this->assertDatabaseHas('caves', [
            'id' => $cave->id,
            'description' => 'Old description',
        ]);

        // Suggested edit should be created
        $this->assertDatabaseHas('suggested_edits', [
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'status' => 'pending',
        ]);

        $edit = SuggestedEdit::where('suggestable_id', $cave->id)->first();
        $this->assertNull($edit->user_id);
        $this->assertArrayHasKey('description', $edit->suggested_data);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_existing_pending_suggested_edit(): void
    {
        $cave = Cave::factory()->create([
            'name' => 'Attborough Swallet',
            'description' => 'Old description',
            'location_lat' => 51.0,
            'location_lng' => -3.0,
        ]);

        SuggestedEdit::create([
            'user_id' => null,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => ['description' => 'Old description'],
            'suggested_data' => ['description' => 'Some intermediate description'],
            'status' => 'pending',
        ]);

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $this->assertDatabaseCount('suggested_edits', 1);

        $edit = SuggestedEdit::first();
        $this->assertStringContainsString('Cave in Dolomitic Conglomerate', $edit->suggested_data['description']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_description_when_mcra_text_already_in_subterra_text(): void
    {
        $cave = Cave::factory()->create([
            'name' => 'Attborough Swallet',
            'description' => "Cave in Dolomitic Conglomerate and Marl with extensive upper series.\n\n[MCRA Registry](https://www.mcra.org.uk/registry/sitedetails.php?id=97)\n\nAdditional Subterra notes.",
            'location_name' => 'Red Quar, Chewton Mendip',
            'location_country' => 'United Kingdom',
        ]);

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $edit = SuggestedEdit::where('suggestable_id', $cave->id)->first();
        if ($edit) {
            $this->assertArrayNotHasKey('description', $edit->suggested_data);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_matches_existing_cave_by_registry_id(): void
    {
        // Cave exists under a different name but has the mcra registry_id
        $cave = Cave::factory()->create([
            'name' => 'Attborough Swallet (Main Entrance)',
            'registry' => 'mcra',
            'registry_id' => '97',
            'description' => 'Old description',
            'location_lat' => 51.26,
            'location_lng' => -2.63,
        ]);

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        // Should not create a duplicate
        $this->assertEquals(1, Cave::where('registry', 'mcra')->where('registry_id', '97')->count());

        // A suggested edit should be created on the existing (renamed) cave
        $edit = SuggestedEdit::where('suggestable_type', Cave::class)
            ->where('suggestable_id', $cave->id)
            ->first();
        $this->assertNotNull($edit);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_persists_registry_id_on_existing_cave_found_by_name(): void
    {
        $cave = Cave::factory()->create([
            'name' => 'Attborough Swallet',
            'description' => 'Old description',
            'location_lat' => 51.26,
            'location_lng' => -2.63,
        ]);

        $this->assertNull($cave->registry);
        $this->assertNull($cave->registry_id);

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $cave->refresh();
        $this->assertEquals('mcra', $cave->registry);
        $this->assertEquals('97', $cave->registry_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_cave_system_with_mcra_references(): void
    {
        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $system = CaveSystem::where('name', 'Attborough Swallet')->first();
        $this->assertNotNull($system);
        $this->assertStringContainsString('[MCRA Registry](https://www.mcra.org.uk/registry/sitedetails.php?id=97)', $system->references);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_suggests_references_update_for_existing_system(): void
    {
        $system = CaveSystem::create([
            'name' => 'Attborough Swallet',
            'slug' => 'attborough-swallet',
            'length' => 0,
            'vertical_range' => 0,
            'references' => '- My own reference',
        ]);

        Cave::factory()->create([
            'name' => 'Attborough Swallet',
            'cave_system_id' => $system->id,
        ]);

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        // References should NOT be directly modified
        $system->refresh();
        $this->assertEquals('- My own reference', $system->references);

        // A suggested edit should be created for the CaveSystem
        $edit = SuggestedEdit::where('suggestable_type', CaveSystem::class)
            ->where('suggestable_id', $system->id)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($edit);
        $this->assertArrayHasKey('references', $edit->suggested_data);
        $this->assertStringContainsString('[MCRA Registry](https://www.mcra.org.uk/registry/sitedetails.php?id=97)', $edit->suggested_data['references']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_duplicate_references_on_existing_system(): void
    {
        $system = CaveSystem::create([
            'name' => 'Attborough Swallet',
            'slug' => 'attborough-swallet',
            'length' => 0,
            'vertical_range' => 0,
            'references' => '- [MCRA Registry](https://www.mcra.org.uk/registry/sitedetails.php?id=97)',
        ]);

        Cave::factory()->create([
            'name' => 'Attborough Swallet',
            'cave_system_id' => $system->id,
        ]);

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        // No suggested edit should be created
        $this->assertDatabaseMissing('suggested_edits', [
            'suggestable_type' => CaveSystem::class,
            'suggestable_id' => $system->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_populates_length_depth_altitude_from_site_details(): void
    {
        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $system = CaveSystem::where('name', 'Attborough Swallet')->first();
        $this->assertNotNull($system);
        $this->assertEquals(244, (int) $system->length);
        $this->assertEquals(46, (int) $system->vertical_range);

        $cave = Cave::where('name', 'Attborough Swallet')->first();
        $this->assertNotNull($cave);
        $this->assertEquals(253, (int) $cave->location_alt);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_location_name_from_site_details(): void
    {
        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Attborough Swallet')->first();
        $this->assertNotNull($cave);
        $this->assertEquals('Red Quar, Chewton Mendip', $cave->location_name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_dry_run_does_not_persist_data(): void
    {
        $this->artisan('sync:mcra-caves --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('caves', ['name' => 'Attborough Swallet']);
        $this->assertDatabaseMissing('caves', ['name' => 'Balch Cave']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_slug_with_mendip_prefix_for_new_caves(): void
    {
        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', [
            'name' => 'Attborough Swallet',
            'slug' => 'mendip_attborough-swallet',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_assigns_portland_tag_instead_of_mendip_for_portland_caves(): void
    {
        Tag::firstOrCreate(['tag' => 'Portland', 'category' => 'region'], ['type' => 'cave']);

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        // Portland Cave (id=500) has "Westcliff, Portland." as its location
        $portlandCave = Cave::where('name', 'Portland Cave')->firstOrFail();
        $mendipCave = Cave::where('name', 'Attborough Swallet')->firstOrFail();

        $this->assertContains('Portland', $portlandCave->tags->pluck('tag')->toArray());
        $this->assertNotContains('Mendip', $portlandCave->tags->pluck('tag')->toArray());

        $this->assertContains('Mendip', $mendipCave->tags->pluck('tag')->toArray());
        $this->assertNotContains('Portland', $mendipCave->tags->pluck('tag')->toArray());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_whitelisted_cave_bypasses_min_length_filter(): void
    {
        // Short Cave Mendip is 50 m — normally filtered out at min-length=200
        // but whitelisting it should force its import.
        $whitelistPath = storage_path('app/mcra_whitelist.txt');
        $originalContent = file_exists($whitelistPath) ? file_get_contents($whitelistPath) : '';
        file_put_contents($whitelistPath, $originalContent."\nShort Cave Mendip\n");

        try {
            $this->artisan('sync:mcra-caves --min-length=200')
                ->assertExitCode(0);

            $this->assertDatabaseHas('caves', ['name' => 'Attborough Swallet']);
            $this->assertDatabaseHas('caves', ['name' => 'Balch Cave']);
            // Should be imported despite being 50 m because it's whitelisted
            $this->assertDatabaseHas('caves', ['name' => 'Short Cave Mendip']);
        } finally {
            if ($originalContent === '') {
                @unlink($whitelistPath);
            } else {
                file_put_contents($whitelistPath, $originalContent);
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_preserves_utf8_characters_in_kml_descriptions(): void
    {
        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Sandy Hole Connection')->firstOrFail();
        $this->assertStringContainsString('≈2,489 m', $cave->description);
        $this->assertStringNotContainsString('â', $cave->description);
    }

    // -----------------------------------------------------------------------
    // Additional mock fixtures
    // -----------------------------------------------------------------------

    private function getMockSiteDetailsUtf8(): string
    {
        return <<<'HTML'
<html><body>
<h1>Sandy Hole Connection</h1>
<p><strong>Somerset.</strong></p>
<table class='rowhover'>
<tr><td>Length:</td><td>2489 m</td></tr>
<tr><td>Depth:</td><td>50 m</td></tr>
<tr><td>Altitude:</td><td>120 m</td></tr>
</table>
</body></html>
HTML;
    }

    private function getMockSiteDetails500Portland(): string
    {
        return <<<'HTML'
<html><body>
<h1>Portland Cave</h1>
<p><strong>Westcliff, Portland.</strong></p>
<table class='rowhover'>
<tr><td>Length:</td><td>120 m</td></tr>
<tr><td>Depth:</td><td>10 m</td></tr>
<tr><td>Altitude:</td><td>5 m</td></tr>
</table>
</body></html>
HTML;
    }
}
