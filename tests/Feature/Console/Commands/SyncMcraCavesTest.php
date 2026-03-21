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

    /**
     * Browse table rows: Name (linked), Location, Tags, Length, Depth, Altitude, System, Bibliography.
     * Detail pages are faked per sitedetails.php?id=….
     */
    private function fakeMcraHttp(string $browseHtml): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($browseHtml) {
            $url = $request->url();
            if (str_contains($url, 'browse.php')) {
                return Http::response($browseHtml, 200);
            }
            if (str_contains($url, 'sitedetails.php') && preg_match('/[?&]id=(\d+)/', $url, $m)) {
                return Http::response($this->detailHtmlForId((int) $m[1]), 200);
            }

            return Http::response('not found', 404);
        });
    }

    private function mcraBrowseFixtureHtml(): string
    {
        $base = 'https://www.mcra.org.uk/registry/';

        return <<<HTML
<html>
<body>
  <div>Page 1 of 1</div>
  <table>
    <tr><th>Name</th><th>Location</th><th>Tags</th><th>Length</th><th>Depth</th><th>Altitude</th><th>System</th><th>Ref</th></tr>
    <tr>
      <td><a href="{$base}sitedetails.php?id=101">MCRA Test Cave</a></td>
      <td>Mendip</td>
      <td>Cave</td>
      <td>300</td>
      <td>50</td>
      <td>210</td>
      <td>Main System</td>
      <td>Mendip Guide 2026</td>
    </tr>
    <tr>
      <td><a href="{$base}sitedetails.php?id=102">Tiny Cave</a></td>
      <td>Mendip</td>
      <td>Cave</td>
      <td>20</td>
      <td>5</td>
      <td>100</td>
      <td>Tiny System</td>
      <td></td>
    </tr>
    <tr>
      <td><a href="{$base}sitedetails.php?id=103">No Access Cave</a></td>
      <td>Mendip</td>
      <td>Cave</td>
      <td>700</td>
      <td>120</td>
      <td>100</td>
      <td>No Access System</td>
      <td></td>
    </tr>
    <tr>
      <td><a href="{$base}sitedetails.php?id=104">Lost Tagged Cave</a></td>
      <td>Mendip</td>
      <td>Lost, Cave</td>
      <td>300</td>
      <td>50</td>
      <td>100</td>
      <td>Lost System</td>
      <td></td>
    </tr>
    <tr>
      <td><a href="{$base}sitedetails.php?id=105">One Metre Cave</a></td>
      <td>Mendip</td>
      <td>Cave</td>
      <td>1</td>
      <td>5</td>
      <td>100</td>
      <td>One System</td>
      <td></td>
    </tr>
    <tr>
      <td><a href="{$base}sitedetails.php?id=106">Unknown Length Cave</a></td>
      <td>Mendip</td>
      <td>Cave</td>
      <td></td>
      <td>5</td>
      <td>100</td>
      <td>Unknown System</td>
      <td></td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    private function detailHtmlForId(int $id): string
    {
        return match ($id) {
            101 => <<<'HTML'
<html><body>
<p>Access information: Open access via club</p>
<p>Registry: | entry
A test cave description with plenty of text for the registry.</p>
<p>WGS84: 51.27000, -2.70000</p>
</body></html>
HTML,
            103 => <<<'HTML'
<html><body><p>Access: No known access at present for this site.</p></body></html>
HTML,
            default => <<<'HTML'
<html><body><p>Access: Open</p></body></html>
HTML,
        };
    }

    public function test_it_imports_new_caves_and_systems(): void
    {
        $this->fakeMcraHttp($this->mcraBrowseFixtureHtml());

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
        $this->fakeMcraHttp($this->mcraBrowseFixtureHtml());

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
        $this->fakeMcraHttp($this->mcraBrowseFixtureHtml());

        $this->artisan('sync:mcra-caves --skip-unknown-access')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('caves', [
            'name' => 'No Access Cave',
        ]);
    }

    public function test_it_imports_no_known_access_entries_by_default(): void
    {
        $this->fakeMcraHttp($this->mcraBrowseFixtureHtml());

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', [
            'name' => 'No Access Cave',
        ]);
    }

    public function test_it_skips_lost_tagged_entries(): void
    {
        $this->fakeMcraHttp($this->mcraBrowseFixtureHtml());

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('caves', [
            'name' => 'Lost Tagged Cave',
        ]);
    }

    public function test_it_skips_unknown_or_one_metre_length_entries(): void
    {
        $this->fakeMcraHttp($this->mcraBrowseFixtureHtml());

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
        $this->fakeMcraHttp($this->mcraBrowseFixtureHtml());

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
        $this->fakeMcraHttp($this->mcraBrowseFixtureHtml());

        $this->artisan('sync:mcra-caves --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseCount('caves', 0);
        $this->assertDatabaseCount('cave_systems', 0);
    }

    public function test_existing_system_references_create_suggested_edit(): void
    {
        $this->fakeMcraHttp($this->mcraBrowseFixtureHtml());

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
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if (str_contains($url, 'browse.php')) {
                return Http::response($this->getBrowseHtml(), 200);
            }
            if (str_contains($url, 'sitedetails.php')) {
                return Http::response('<html><body><p>Access: Open</p></body></html>', 200);
            }

            return Http::response('not found', 404);
        });

        $this->artisan('sync:mcra-caves --dry-run')
            ->assertExitCode(0);
    }

    public function test_real_run_fails_when_browse_feed_parses_zero_rows(): void
    {
        Http::fake([
            '*' => Http::response('<html><body><div>Page 1 of 1</div><p>No table rows</p></body></html>', 200),
        ]);

        $this->artisan('sync:mcra-caves')
            ->assertExitCode(1);
    }

    public function test_dry_run_succeeds_when_browse_feed_parses_zero_rows(): void
    {
        Http::fake([
            '*' => Http::response('<html><body><div>Page 1 of 1</div><p>No table rows</p></body></html>', 200),
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
