<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Models\Cave;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The FODCCAG KML feed (placemarks.php) returns HTTP 500 while the rest of the
 * registry stays healthy, so sync:fod-caves falls back to sweeping
 * sitedetails.php by ID. Fixtures mirror the real page markup.
 */
class SyncFodCavesFallbackTest extends TestCase
{
    use RefreshDatabase;

    private const KML = 'http://www.fodccag.org.uk/registry/googleEarth/placemarks.php*';

    private const DETAILS = 'http://www.fodccag.org.uk/registry/sitedetails.php*';

    protected function setUp(): void
    {
        parent::setUp();

        // The importer requires these tags to already exist.
        Tag::firstOrCreate(['tag' => 'Cave', 'category' => 'type'], ['type' => 'cave']);
        Tag::firstOrCreate(['tag' => 'Forest of Dean', 'category' => 'region'], ['type' => 'cave']);
    }

    /** A site-details page in the registry's real markup. */
    private function detailsPage(
        string $name,
        string $coords = '51.67371, -2.65622',
        string $length = '620 m',
        string $depth = '28 m',
    ): string {
        return <<<HTML
        <!DOCTYPE html><html><body>
        <h1>{$name}</h1>
        <p><strong>Dennel Hill, Tidenham.</strong></p>
        <table class='rowhover'>
        <tr><td>NGR:</td><td>ST 54719 97420</td></tr>
        <tr><td>WGS84:</td><td>{$coords}</td></tr>
        <tr><td>Length:</td><td>{$length}</td></tr>
        <tr><td>Depth:</td><td>{$depth}</td></tr>
        <tr><td>Altitude:</td><td>16 m</td></tr>
        </table>
        <p>A 6m fixed ladder descends to a series of crawls and squeezes.</p>
        </body></html>
        HTML;
    }

    /** The stub the registry serves for an ID that does not exist. */
    private function missingPage(): string
    {
        return '<!DOCTYPE html><html><body><p>&nbsp;</p></body></html>';
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_falls_back_to_a_site_id_sweep_when_the_kml_feed_returns_500(): void
    {
        $pages = [
            1 => $this->detailsPage('Ban-y-gor Cave'),
            2 => $this->detailsPage('Slade Brook Cave', '51.74595, -2.62853', '250 m', '12 m'),
        ];

        Http::fake([
            self::KML => Http::response('', 500),
            self::DETAILS => function ($request) use ($pages) {
                parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $q);
                $id = (int) ($q['id'] ?? 0);

                return Http::response($pages[$id] ?? $this->missingPage(), 200);
            },
        ]);

        $this->artisan('sync:fod-caves', ['--min-length' => 10])
            ->assertExitCode(0);

        $this->assertSame(2, Cave::where('registry', 'fod')->count());

        $cave = Cave::where('registry', 'fod')->where('registry_id', '1')->first();
        $this->assertNotNull($cave, 'Expected the swept cave to be imported.');
        $this->assertSame('Ban-y-gor Cave', $cave->name);
        $this->assertEqualsWithDelta(51.67371, (float) $cave->location_lat, 0.00001);
        $this->assertEqualsWithDelta(-2.65622, (float) $cave->location_lng, 0.00001);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_swept_entries_that_have_no_recorded_coordinates(): void
    {
        $pages = [
            1 => $this->detailsPage('Ban-y-gor Cave'),
            2 => str_replace(
                '<td>51.67371, -2.65622</td>',
                '<td>Not recorded</td>',
                $this->detailsPage('Whittington Stone Mine')
            ),
        ];

        Http::fake([
            self::KML => Http::response('', 500),
            self::DETAILS => function ($request) use ($pages) {
                parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $q);
                $id = (int) ($q['id'] ?? 0);

                return Http::response($pages[$id] ?? $this->missingPage(), 200);
            },
        ]);

        $this->artisan('sync:fod-caves', ['--min-length' => 10])->assertExitCode(0);

        $this->assertSame(1, Cave::where('registry', 'fod')->count());
        $this->assertNull(Cave::where('registry', 'fod')->where('registry_id', '2')->first());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prefers_the_kml_feed_and_does_not_sweep_when_it_works(): void
    {
        $kml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <kml xmlns="http://www.opengis.net/kml/2.2"><Document>
        <Placemark><name>Ban-y-gor Cave</name>
        <description><![CDATA[<a href="sitedetails.php?id=1">details</a>]]></description>
        <Point><coordinates>-2.65622,51.67371,0</coordinates></Point>
        </Placemark></Document></kml>
        XML;

        Http::fake([
            self::KML => Http::response($kml, 200),
            self::DETAILS => Http::response($this->detailsPage('Ban-y-gor Cave'), 200),
        ]);

        $this->artisan('sync:fod-caves', ['--min-length' => 10])->assertExitCode(0);

        // A sweep would probe far beyond the handful of IDs the KML references.
        $detailRequests = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains((string) $pair[0]->url(), 'sitedetails.php'))
            ->count();

        $this->assertLessThan(10, $detailRequests, 'KML path should not trigger an ID sweep.');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_still_fails_when_both_the_kml_feed_and_the_sweep_are_unavailable(): void
    {
        Http::fake([
            self::KML => Http::response('', 500),
            self::DETAILS => Http::response('', 500),
        ]);

        $this->artisan('sync:fod-caves')->assertExitCode(1);
    }
}
