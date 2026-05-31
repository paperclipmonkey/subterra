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

class SyncCnccCavesTest extends TestCase
{
    use RefreshDatabase;

    private const INDEX_URL_PATTERN = '*cncc.org.uk/caving/caves/*';

    protected function setUp(): void
    {
        parent::setUp();

        Tag::firstOrCreate(['tag' => 'Cave', 'category' => 'type'], ['type' => 'cave']);
        Tag::firstOrCreate(['tag' => 'Yorkshire', 'category' => 'region'], ['type' => 'cave']);
    }

    /** Set up the default Http fakes for a normal sync run. */
    private function fakeDefaultHttp(): void
    {
        Http::fake([
            self::INDEX_URL_PATTERN => Http::response($this->getMockIndex(), 200),
            '*cncc.org.uk/cave/alum-pot' => Http::response($this->getMockDetailAlumPot(), 200),
            '*cncc.org.uk/cave/lost-johns-cave' => Http::response($this->getMockDetailLostJohns(), 200),
            '*cncc.org.uk/cave/blocked-cave' => Http::response($this->getMockDetailGeneric(), 200),
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
<table>
  <tr><th>Cave</th><th>Info</th><th>Access</th></tr>
  <tr>
    <td><a class="me-2" href="cave/alum-pot">Alum Pot</a>
        <span>(Alum Pot)</span></td>
    <td></td>
    <td>Call at house</td>
  </tr>
  <tr>
    <td><a class="me-2" href="cave/lost-johns-cave">Lost Johns&#039; Cave</a>
        <span>(Leck Fell)</span></td>
    <td>Route description available</td>
    <td>Book online</td>
  </tr>
  <tr>
    <td><a class="me-2" href="cave/blocked-cave">Blocked Cave</a>
        <span>(Test Region)</span></td>
    <td></td>
    <td>No restrictions</td>
  </tr>
</table>
</body>
</html>
HTML;
    }

    private function getMockDetailAlumPot(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<body>
<h1>Alum Pot (Alum Pot)</h1>
<p><a href="https://www.bing.com/maps?cp=54.17545~-2.34649">SD 77480 75574</a></p>
<p><a href="https://www.bing.com/maps?cp=54.17545~-2.34649">54.17545399624298, -2.3464968212268102</a></p>
</body>
</html>
HTML;
    }

    private function getMockDetailLostJohns(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<body>
<h1>Lost Johns&#039; Cave (Leck Fell)</h1>
<p><a href="https://www.bing.com/maps?cp=54.20237~-2.50622">SD 67075 78632</a></p>
<p><a href="https://www.bing.com/maps?cp=54.20237~-2.50622">54.2023719494235, -2.50622839036934</a></p>
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
<h1>Blocked Cave (Test Region)</h1>
<p>No location available.</p>
</body>
</html>
HTML;
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_new_cncc_caves(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:cncc-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Alum Pot']);
        $this->assertDatabaseHas('caves', ['name' => "Lost Johns' Cave"]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_registry_and_registry_id_on_new_caves(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:cncc-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Alum Pot')->firstOrFail();
        $this->assertEquals('cncc', $cave->registry);
        $this->assertEquals('alum-pot', $cave->registry_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_location_name_from_region(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:cncc-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', [
            'name' => 'Alum Pot',
            'location_name' => 'Alum Pot',
        ]);

        $this->assertDatabaseHas('caves', [
            'name' => "Lost Johns' Cave",
            'location_name' => 'Leck Fell',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_coordinates_from_detail_page(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:cncc-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Alum Pot')->firstOrFail();
        $this->assertEqualsWithDelta(54.17545, $cave->location_lat, 0.0001);
        $this->assertEqualsWithDelta(-2.34649, $cave->location_lng, 0.001);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_cncc_slug_prefix(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:cncc-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', [
            'name' => 'Alum Pot',
            'slug' => 'cncc_alum-pot',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_cave_and_yorkshire_tags(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:cncc-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Alum Pot')->firstOrFail();
        $tagNames = $cave->tags->pluck('tag')->toArray();

        $this->assertContains('Cave', $tagNames);
        $this->assertContains('Yorkshire', $tagNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_a_cave_system_for_each_cave(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:cncc-caves')
            ->assertExitCode(0);

        $this->assertDatabaseHas('cave_systems', ['name' => 'Alum Pot']);
        $this->assertDatabaseHas('cave_systems', ['name' => "Lost Johns' Cave"]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_cncc_reference_link_to_cave_system(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:cncc-caves')
            ->assertExitCode(0);

        $system = CaveSystem::where('name', 'Alum Pot')->firstOrFail();
        $this->assertStringContainsString('cncc.org.uk/cave/alum-pot', $system->references);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_cncc_link_to_cave_description_and_access_info(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:cncc-caves')
            ->assertExitCode(0);

        $cave = Cave::where('name', 'Alum Pot')->firstOrFail();
        $this->assertStringContainsString('cncc.org.uk/cave/alum-pot', $cave->description);
        $this->assertStringContainsString('Alum Pot', $cave->description);
        $this->assertStringContainsString('cncc.org.uk/cave/alum-pot', $cave->access_info);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_respects_the_blocklist(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:cncc-caves --blocklist="Blocked Cave"')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Alum Pot']);
        $this->assertDatabaseMissing('caves', ['name' => 'Blocked Cave']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_import_data_in_dry_run_mode(): void
    {
        $this->fakeDefaultHttp();

        $this->artisan('sync:cncc-caves --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseCount('caves', 0);
        $this->assertDatabaseCount('cave_systems', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_suggested_edit_for_existing_cave_with_coordinate_changes(): void
    {
        $this->fakeDefaultHttp();

        $system = CaveSystem::factory()->create(['name' => 'Alum Pot', 'slug' => 'alum-pot']);
        $cave = Cave::factory()->create([
            'name' => 'Alum Pot',
            'cave_system_id' => $system->id,
            'location_lat' => 51.0,
            'location_lng' => -3.0,
        ]);

        $this->artisan('sync:cncc-caves')
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

        $system = CaveSystem::factory()->create([
            'name' => 'Alum Pot',
            'slug' => 'alum-pot',
            'references' => '- [CNCC Cave Page](https://cncc.org.uk/cave/alum-pot)',
        ]);
        $cnccLink = '[CNCC page for Alum Pot](https://cncc.org.uk/cave/alum-pot)';
        Cave::factory()->create([
            'name' => 'Alum Pot',
            'cave_system_id' => $system->id,
            'description' => 'For more information see '.$cnccLink.'.',
            'access_info' => 'For more information see '.$cnccLink.'.',
            'location_name' => 'Alum Pot',
            'location_lat' => 54.17545399624298,
            'location_lng' => -2.3464968212268102,
            'location_country' => 'United Kingdom',
        ]);

        $this->artisan('sync:cncc-caves')
            ->assertExitCode(0);

        $this->assertDatabaseCount('suggested_edits', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_an_empty_index_gracefully(): void
    {
        Http::fake([
            self::INDEX_URL_PATTERN => Http::response('<html><body><table></table></body></html>', 200),
        ]);

        $this->artisan('sync:cncc-caves')
            ->assertExitCode(0);

        $this->assertDatabaseCount('caves', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_a_failed_index_request(): void
    {
        Http::fake([
            self::INDEX_URL_PATTERN => Http::response('', 503),
        ]);

        $this->artisan('sync:cncc-caves')
            ->assertExitCode(1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_a_missing_detail_page_gracefully(): void
    {
        Http::fake([
            self::INDEX_URL_PATTERN => Http::response($this->getMockIndex(), 200),
            '*cncc.org.uk/cave/alum-pot' => Http::response('', 404),
            '*cncc.org.uk/cave/lost-johns-cave' => Http::response($this->getMockDetailLostJohns(), 200),
            '*cncc.org.uk/cave/blocked-cave' => Http::response($this->getMockDetailGeneric(), 200),
        ]);

        $this->artisan('sync:cncc-caves')
            ->assertExitCode(0);

        // Alum Pot should still be created, just with no coordinates
        $this->assertDatabaseHas('caves', ['name' => 'Alum Pot']);
        $cave = Cave::where('name', 'Alum Pot')->firstOrFail();
        $this->assertEquals(0.0, $cave->location_lat);
    }
}
