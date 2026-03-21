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

        Tag::firstOrCreate(['tag' => 'Cave', 'category' => 'type'], ['type' => 'cave']);
        Tag::firstOrCreate(['tag' => 'Mendip', 'category' => 'region'], ['type' => 'cave']);
    }

    private function fakeXmlFeed(): void
    {
        Http::fake([
            '*' => Http::response($this->getMockXml(), 200),
        ]);
    }

    private function getMockXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Registry>
    <Region name="Mendip">
        <Entry id="1" length="300" dep="50" lat="51.27000" lng="-2.70000" alt="210">
            <Name>MCRA Test Cave</Name>
            <System>Main System</System>
            <Desc>A test cave description.</Desc>
            <Access>Open access via club</Access>
            <Reference>Mendip Guide 2026</Reference>
        </Entry>
        <Entry id="2" length="20" dep="5" lat="51.25000" lng="-2.60000">
            <Name>Tiny Cave</Name>
            <Desc>Small cave for testing min length.</Desc>
            <Access>Open</Access>
        </Entry>
        <Entry id="3" length="700" dep="120" lat="51.26000" lng="-2.65000">
            <Name>No Access Cave</Name>
            <Desc>Should be skipped by access phrase.</Desc>
            <Access>No known access at present</Access>
        </Entry>
        <Entry id="4" length="300" dep="50" lat="51.28000" lng="-2.68000">
            <Name>Lost Tagged Cave</Name>
            <Desc>Listed but cave is lost.</Desc>
            <Tags>Lost, Cave</Tags>
        </Entry>
        <Entry id="5" length="1" dep="5" lat="51.29000" lng="-2.69000">
            <Name>One Metre Cave</Name>
            <Desc>Tiny cave record.</Desc>
        </Entry>
        <Entry id="6" dep="5" lat="51.29500" lng="-2.69500">
            <Name>Unknown Length Cave</Name>
            <Desc>Length unknown.</Desc>
        </Entry>
    </Region>
</Registry>
XML;
    }

    public function test_it_imports_new_caves_and_systems(): void
    {
        $this->fakeXmlFeed();

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', [
            'name' => 'MCRA Test Cave',
            'location_name' => 'Mendip',
        ]);

        $this->assertDatabaseHas('cave_systems', [
            'name' => 'Main System',
            'length' => 300,
            'vertical_range' => 50,
        ]);

        $this->assertDatabaseCount('suggested_edits', 0);
    }

    public function test_it_creates_suggested_edit_for_existing_cave_with_differences(): void
    {
        $this->fakeXmlFeed();

        $cave = Cave::factory()->create([
            'name' => 'MCRA Test Cave',
            'description' => 'Original local description',
            'location_lat' => 51.0,
            'location_lng' => -2.0,
        ]);

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('suggested_edits', [
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'status' => 'pending',
        ]);

        $edit = SuggestedEdit::where('suggestable_type', Cave::class)
            ->where('suggestable_id', $cave->id)
            ->first();

        $this->assertNotNull($edit);
        $this->assertArrayHasKey('description', $edit->suggested_data);
        $this->assertStringContainsString('MCRA Registry', $edit->suggested_data['description']);
    }

    public function test_it_skips_no_known_access_entries(): void
    {
        $this->fakeXmlFeed();

        $this->artisan('sync:mcra-caves --skip-unknown-access')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('caves', [
            'name' => 'No Access Cave',
        ]);
    }

    public function test_it_imports_no_known_access_entries_by_default(): void
    {
        $this->fakeXmlFeed();

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', [
            'name' => 'No Access Cave',
        ]);
    }

    public function test_it_skips_lost_tagged_entries(): void
    {
        $this->fakeXmlFeed();

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('caves', [
            'name' => 'Lost Tagged Cave',
        ]);
    }

    public function test_it_skips_unknown_or_one_metre_length_entries(): void
    {
        $this->fakeXmlFeed();

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('caves', [
            'name' => 'One Metre Cave',
        ]);
        $this->assertDatabaseMissing('caves', [
            'name' => 'Unknown Length Cave',
        ]);
    }

    public function test_blocklist_and_whitelist_are_applied(): void
    {
        $this->fakeXmlFeed();

        $this->artisan('sync:mcra-caves --min-length=250 --blocklist="MCRA Test Cave" --whitelist="Tiny Cave"')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('caves', [
            'name' => 'MCRA Test Cave',
        ]);

        $this->assertDatabaseHas('caves', [
            'name' => 'Tiny Cave',
        ]);
    }

    public function test_dry_run_does_not_persist(): void
    {
        $this->fakeXmlFeed();

        $this->artisan('sync:mcra-caves --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseCount('caves', 0);
        $this->assertDatabaseCount('cave_systems', 0);
    }

    public function test_existing_system_references_create_suggested_edit(): void
    {
        $this->fakeXmlFeed();

        $system = CaveSystem::create([
            'name' => 'Main System',
            'slug' => 'main-system',
            'references' => '- Existing local ref',
            'length' => 0,
            'vertical_range' => 0,
        ]);

        Cave::factory()->create([
            'name' => 'MCRA Test Cave',
            'cave_system_id' => $system->id,
        ]);

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $system->refresh();
        $this->assertEquals('- Existing local ref', $system->references);

        $edit = SuggestedEdit::where('suggestable_type', CaveSystem::class)
            ->where('suggestable_id', $system->id)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($edit);
        $this->assertArrayHasKey('references', $edit->suggested_data);
        $this->assertStringContainsString('MCRA Registry', $edit->suggested_data['references']);
        $this->assertStringContainsString('Mendip Guide 2026', $edit->suggested_data['references']);
    }

    public function test_it_parses_browse_html_feed(): void
    {
        Http::fake([
            '*' => Http::response($this->getBrowseHtml(), 200),
        ]);

        $this->artisan('sync:mcra-caves --dry-run')
            ->assertExitCode(0);
    }

    private function getBrowseHtml(): string
    {
        return <<<'HTML'
<html>
<body>
  <h1>Mendip Cave Registry Browser</h1>
  <div>Page 1 of 1</div>
  <table>
    <tr><th>Name</th><th>Location</th><th>Tags</th><th>Length</th><th>Depth</th><th>Altitude</th></tr>
    <tr>
      <td><a href="sitedetails.php?id=123">Sample Cave</a></td>
      <td>Burrington Combe</td>
      <td>Cave, CROW</td>
      <td>1006</td>
      <td>43</td>
      <td>134</td>
    </tr>
  </table>
</body>
</html>
HTML;
    }
}
