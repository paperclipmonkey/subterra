<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\UpsertsBotSuggestedEdits;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use App\Support\CaveName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sync UK cave entrances from OpenStreetMap via the Overpass API.
 *
 * Unlike the registry syncs (CNCC, MCRA, …) this source carries no agreement
 * obligations: OSM data is published under the Open Database Licence (ODbL),
 * which permits reuse with attribution and share-alike. Every imported cave is
 * credited back to OpenStreetMap in its description.
 *
 * By default only *named* entrances are imported — an unnamed `cave_entrance`
 * node is almost always a minor dig or shake hole, whereas named ones are the
 * recognised caves people actually look for. Pass --include-unnamed to widen.
 */
class SyncOsmCaves extends Command
{
    use UpsertsBotSuggestedEdits;

    protected $signature = 'sync:osm-caves
                            {--dry-run : Parse without inserting data}
                            {--include-unnamed : Also import entrances with no name tag}
                            {--blocklist= : Comma-separated list of cave names to always skip}';

    protected $description = 'Sync UK cave entrances from OpenStreetMap (Overpass API), licensed under ODbL';

    private const REGISTRY = 'osm';

    private const OVERPASS_ENDPOINT = 'https://overpass-api.de/api/interpreter';

    /** Overpass rejects requests without a User-Agent (HTTP 406). */
    private const USER_AGENT = 'Subterra cave sync (+https://subterra.app)';

    /** Public OSM URL for an element, used for attribution links. */
    private const OSM_NODE_URL = 'https://www.openstreetmap.org/node/';

    /**
     * Region tag bounding boxes: [latMin, latMax, lngMin, lngMax].
     *
     * OSM data is UK-wide and carries no region of its own, so the region tag
     * (used elsewhere for filtering/visibility) is resolved from coordinates.
     * Boxes are deliberately tight around each caving area; anything outside
     * them is imported with no region tag (just the generic Cave type tag).
     * Keyed by the exact `tags.tag` value in the region category.
     *
     * @var array<string, array{float, float, float, float}>
     */
    private const REGION_BOXES = [
        'Portland' => [50.51, 50.58, -2.47, -2.42],   // checked before Mendip — tiny, distinct
        'Mendip' => [51.15, 51.40, -2.90, -2.45],
        'Forest of Dean' => [51.70, 51.92, -2.72, -2.48],
        'South Wales' => [51.55, 52.20, -4.35, -2.95],
        'North Wales' => [52.75, 53.45, -4.60, -3.05],
        'Peak District' => [52.98, 53.60, -2.12, -1.40],
        'Northern' => [53.85, 54.75, -2.85, -1.75],
        'Devon' => [50.25, 51.05, -4.55, -3.40],
        'Scotland' => [54.95, 59.20, -8.20, -1.90],
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $includeUnnamed = (bool) $this->option('include-unnamed');
        $blocklistNamesLower = array_map('strtolower', $this->getBlocklist());

        $this->info('Fetching cave entrances from OpenStreetMap (Overpass API)...');

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(180)
                ->get(self::OVERPASS_ENDPOINT, ['data' => $this->overpassQuery()]);

            if (!$response->successful()) {
                $this->error('Failed to query Overpass API (status '.$response->status().')');

                return 1;
            }
        } catch (\Exception $e) {
            $this->error('HTTP request failed: '.$e->getMessage());

            return 1;
        }

        $entries = $this->parseOverpass($response->json(), $includeUnnamed);

        if (empty($entries)) {
            $this->warn('No cave entrances found in the OSM response.');

            return 0;
        }

        $this->info('Found '.count($entries).' usable cave entrances in OSM.');

        DB::beginTransaction();

        $importedCount = 0;
        $skippedCount = 0;
        $newCaveCount = 0;
        $suggestedEditCount = 0;
        $noOpCount = 0;

        try {
            foreach ($entries as $entry) {
                $name = $entry['name'];
                $osmId = $entry['osm_id'];
                $lat = $entry['lat'];
                $lng = $entry['lng'];
                $alt = $entry['alt'];

                if (in_array(strtolower($name), $blocklistNamesLower)) {
                    $this->line("<fg=gray>  ⊘ Skipped (blocklist):</> {$name}");
                    ++$skippedCount;
                    continue;
                }

                $regionTagName = $this->resolveRegionTagName($lat, $lng);

                $baseSlug = 'osm_'.Str::slug($name);
                $existingCave = Cave::where('registry', self::REGISTRY)->where('registry_id', $osmId)->first()
                    ?? CaveName::findCaveForRegistry($name, $baseSlug, self::REGISTRY, $lat, $lng);

                $this->line("Processing: {$name} <fg=gray>(".($regionTagName ?? 'no region').')</>');
                ++$importedCount;

                if ($dryRun) {
                    continue;
                }

                // -----------------------------------------------------------------
                // 1. Cave System
                // -----------------------------------------------------------------
                // An adopted cave keeps its real system: find-or-creating one from
                // the cave name here would orphan references onto an empty system
                // when the cave belongs to a differently-named system.
                $systemIsNew = false;
                if ($existingCave && $existingCave->system) {
                    $system = $existingCave->system;
                } else {
                    $systemName = $name;
                    $systemSlug = Str::slug($systemName);
                    $system = CaveName::findSystemForRegistry($systemName, $systemSlug, self::REGISTRY, $lat, $lng);

                    if (!$system) {
                        $system = CaveSystem::create([
                            'name' => $systemName,
                            'slug' => $this->uniqueSlug($systemSlug, 'cave_systems'),
                            'length' => 0,
                            'vertical_range' => 0,
                        ]);
                        $systemIsNew = true;
                    }
                }

                // -----------------------------------------------------------------
                // 2. Registry reference on the cave system
                // -----------------------------------------------------------------
                $osmUrl = self::OSM_NODE_URL.$osmId;
                $registryLinkMd = '- [OpenStreetMap]('.$osmUrl.')';

                if ($systemIsNew) {
                    $system->references = $registryLinkMd;
                    $system->save();
                } else {
                    $existingRefsLower = strtolower($system->references ?? '');
                    if (!str_contains($existingRefsLower, strtolower($osmUrl))) {
                        $existingRefs = !empty($system->references)
                            ? explode("\n", $system->references)
                            : [];

                        $suggestedValue = implode("\n", array_merge($existingRefs, [$registryLinkMd]));

                        $this->upsertBotSuggestedEdit(
                            CaveSystem::class,
                            $system->id,
                            ['references' => $system->references],
                            ['references' => $suggestedValue],
                        );
                        ++$suggestedEditCount;
                    }
                }

                // -----------------------------------------------------------------
                // 3. Cave data
                // -----------------------------------------------------------------
                $description = "See [{$name} on OpenStreetMap]({$osmUrl}).\n\n"
                    .'Data © OpenStreetMap contributors, available under the '
                    .'[Open Database Licence (ODbL)](https://opendatacommons.org/licenses/odbl/).';

                $caveData = [
                    'description' => $description,
                    'location_name' => $regionTagName ?? '',
                    'location_country' => 'United Kingdom',
                    'location_lat' => $lat,
                    'location_lng' => $lng,
                ];
                if ($alt !== null) {
                    $caveData['location_alt'] = $alt;
                }

                if ($existingCave) {
                    $coordKeys = ['location_lat', 'location_lng', 'location_alt'];
                    $textKeys = ['location_name', 'description'];
                    $differences = [];

                    // A same-named OSM node with a different registry_id is another
                    // entrance of a cave we already track — proposing its coordinates
                    // would flip-flop the cave between entrances on every run.
                    $isOtherOsmEntrance = $existingCave->registry === self::REGISTRY
                        && !empty($existingCave->registry_id)
                        && $existingCave->registry_id !== $osmId;

                    foreach ($caveData as $key => $value) {
                        if (in_array($key, $coordKeys)) {
                            $existingRounded = round((float) $existingCave->$key, 5);
                            $newRounded = round((float) $value, 5);
                            if (!$isOtherOsmEntrance && $newRounded != 0 && $existingRounded !== $newRounded) {
                                $differences[$key] = $value;
                            }
                        } elseif (in_array($key, $textKeys)) {
                            // The generated OSM text is attribution boilerplate, so it
                            // only fills blanks — never replaces a curated value.
                            if (!empty($value) && empty($existingCave->$key)) {
                                $differences[$key] = $value;
                            }
                        } elseif ($existingCave->$key !== $value) {
                            $differences[$key] = $value;
                        }
                    }

                    if (!empty($differences)) {
                        $originalData = [];
                        foreach (array_keys($differences) as $key) {
                            $val = $existingCave->$key;
                            $originalData[$key] = in_array($key, $coordKeys)
                                ? round((float) $val, 5)
                                : $val;
                        }

                        $edit = $this->upsertBotSuggestedEdit(Cave::class, $existingCave->id, $originalData, $differences);
                        $action = $edit->wasRecentlyCreated ? 'Created' : 'Updated';
                        $this->line("<fg=yellow>  ✏ {$action} suggested edit:</> {$name} <fg=gray>[".implode(', ', array_keys($differences)).']</>');
                        ++$suggestedEditCount;
                    } else {
                        $this->line("<fg=blue>  ⊘ No changes:</> {$name}");
                        ++$noOpCount;
                    }

                    if (empty($existingCave->registry) || empty($existingCave->registry_id)) {
                        $existingCave->registry = self::REGISTRY;
                        $existingCave->registry_id = $osmId;
                        $existingCave->save();
                    }

                    $cave = $existingCave;
                } else {
                    $cave = Cave::create(array_merge([
                        'name' => $name,
                        'slug' => $this->uniqueSlug($baseSlug, 'caves'),
                        'cave_system_id' => $system->id,
                        'registry' => self::REGISTRY,
                        'registry_id' => $osmId,
                    ], $caveData));
                    $this->line("<fg=green>  ✚ New cave created:</> {$name}");
                    ++$newCaveCount;
                }

                // -----------------------------------------------------------------
                // 4. Sync tags
                // -----------------------------------------------------------------
                $tagCave = Tag::firstOrCreate(
                    ['tag' => 'Cave', 'category' => 'type'],
                    ['type' => 'cave']
                );
                $tagIds = [$tagCave->id];

                if ($regionTagName !== null) {
                    $regionTag = Tag::where('tag', $regionTagName)->where('category', 'region')->first();
                    if ($regionTag) {
                        $tagIds[] = $regionTag->id;
                    }
                }

                $cave->tags()->syncWithoutDetaching(array_unique($tagIds));
            }

            if (!$dryRun) {
                DB::commit();
                $this->newLine();
                $this->info("Sync completed: {$importedCount} processed, {$skippedCount} skipped.");
                $this->line("  <fg=green>✚ New caves:</> {$newCaveCount}");
                $this->line("  <fg=yellow>✏ Suggested edits:</> {$suggestedEditCount}");
                $this->line("  <fg=blue>⊘ No changes:</> {$noOpCount}");
            } else {
                DB::rollBack();
                $this->info("Dry run completed: {$importedCount} would be processed, {$skippedCount} skipped.");
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error during sync: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return 1;
        }

        return 0;
    }

    /**
     * The Overpass QL query: all cave_entrance nodes within Great Britain.
     */
    private function overpassQuery(): string
    {
        return <<<'OVERPASS'
[out:json][timeout:180];
area["ISO3166-1"="GB"][admin_level=2]->.uk;
node["natural"="cave_entrance"](area.uk);
out body;
OVERPASS;
    }

    /**
     * Parse the Overpass JSON payload into cave entries.
     *
     * Each entry has: name, osm_id, lat, lng, alt.
     *
     * Entries are deduplicated by normalised name: a cave with several mapped
     * entrances appears as several same-named nodes, and processing more than
     * one would generate flip-flopping coordinate edits on every run. Only the
     * first node per name is kept.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array<int, array{name: string, osm_id: string, lat: float, lng: float, alt: float|null}>
     */
    private function parseOverpass(?array $payload, bool $includeUnnamed): array
    {
        $elements = $payload['elements'] ?? null;
        if (!is_array($elements)) {
            return [];
        }

        $entries = [];
        $seenNames = [];
        foreach ($elements as $el) {
            if (($el['type'] ?? null) !== 'node') {
                continue;
            }
            if (!isset($el['lat'], $el['lon'], $el['id'])) {
                continue;
            }

            $tags = $el['tags'] ?? [];
            $name = isset($tags['name']) ? trim((string) $tags['name']) : '';

            if ($name === '') {
                if (!$includeUnnamed) {
                    continue;
                }
                $name = 'Cave entrance #'.$el['id'];
            }

            $nameKey = CaveName::normalise($name);
            if (isset($seenNames[$nameKey])) {
                continue;
            }
            $seenNames[$nameKey] = true;

            $entries[] = [
                'name' => $name,
                'osm_id' => (string) $el['id'],
                'lat' => round((float) $el['lat'], 7),
                'lng' => round((float) $el['lon'], 7),
                'alt' => $this->parseElevation($tags['ele'] ?? null),
            ];
        }

        return $entries;
    }

    /**
     * Parse an OSM `ele` tag (e.g. "412", "412.5", "412 m") into metres.
     */
    private function parseElevation(mixed $ele): ?float
    {
        if ($ele === null) {
            return null;
        }

        if (preg_match('/-?\d+(\.\d+)?/', (string) $ele, $m)) {
            return (float) $m[0];
        }

        return null;
    }

    /**
     * Resolve the region tag name for a coordinate, or null if outside all
     * known caving areas. Returns the exact `tags.tag` value to look up.
     */
    private function resolveRegionTagName(float $lat, float $lng): ?string
    {
        foreach (self::REGION_BOXES as $tagName => [$latMin, $latMax, $lngMin, $lngMax]) {
            if ($lat >= $latMin && $lat <= $latMax && $lng >= $lngMin && $lng <= $lngMax) {
                return $tagName;
            }
        }

        return null;
    }

    /**
     * Build a unique slug by appending a numeric suffix when collisions occur.
     */
    private function uniqueSlug(string $base, string $table): string
    {
        $slug = $base;
        $suffix = 2;

        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            ++$suffix;
        }

        return $slug;
    }

    /**
     * Return an array of blocklisted names from the --blocklist option.
     *
     * @return array<string>
     */
    private function getBlocklist(): array
    {
        $raw = $this->option('blocklist') ?? '';

        if (empty(trim($raw))) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $raw)));
    }
}
