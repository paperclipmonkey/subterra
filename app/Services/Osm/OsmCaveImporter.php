<?php

declare(strict_types=1);

namespace App\Services\Osm;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use App\Support\CaveName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Cave entrances from OpenStreetMap (Overpass API), licensed under the ODbL.
 *
 * OSM data is kept separate from registry data so the open portion stays
 * cleanly identifiable (ODbL is share-alike; registry data is not ours to
 * relicense):
 *  - A cave that doesn't exist yet is created as an OSM-sourced record
 *    (registry = 'osm') carrying ODbL attribution.
 *  - A cave we already hold (from a registry or entered by hand) is only
 *    *linked* via `osm_node_id`. OSM coordinates or text are never proposed
 *    for it.
 */
class OsmCaveImporter
{
    public const REGISTRY = 'osm';

    public const NODE_URL = 'https://www.openstreetmap.org/node/';

    public const ATTRIBUTION = 'Data © OpenStreetMap contributors, available under the '
        .'[Open Database Licence (ODbL)](https://opendatacommons.org/licenses/odbl/).';

    /**
     * Public Overpass instances, tried in order. The main instance regularly
     * returns transient errors (dispatcher/rate-limit) or times out, so each is
     * retried briefly before falling back to the next.
     */
    private const OVERPASS_ENDPOINTS = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.private.coffee/api/interpreter',
    ];

    private const ATTEMPTS_PER_ENDPOINT = 2;

    private const RETRY_DELAY_SECONDS = 10;

    /** Overpass rejects requests without a User-Agent (HTTP 406). */
    private const USER_AGENT = 'Subterra cave sync (+https://subterra.app)';

    /** Same-named nodes closer than this are entrances of one cave, not two caves. */
    private const SAME_CAVE_KM = 1.0;

    /** An existing same-named cave within this distance is the same place. */
    private const SAME_PLACE_KM = 10.0;

    /**
     * Region tag bounding boxes: [latMin, latMax, lngMin, lngMax].
     *
     * Used both to scope a query to one caving area and to resolve the region
     * tag of an imported cave. Boxes are deliberately tight around each caving
     * area. Order matters where boxes touch: the first match wins. Keyed by the
     * exact `tags.tag` value in the region category.
     *
     * @var array<string, array{float, float, float, float}>
     */
    public const REGION_BOXES = [
        'Portland' => [50.51, 50.58, -2.47, -2.42],   // checked before Mendip — tiny, distinct
        'Mendip' => [51.15, 51.40, -2.90, -2.45],
        'Forest of Dean' => [51.70, 51.92, -2.72, -2.48],
        'South Wales' => [51.55, 52.20, -4.35, -2.95],
        'North Wales' => [52.75, 53.45, -4.60, -3.05],
        'Peak District' => [52.98, 53.60, -2.12, -1.40],
        'Northern' => [53.85, 54.75, -2.85, -1.75],
        'Devon' => [50.25, 51.05, -4.55, -3.40],
        // Before Scotland; stops short of Kintyre (55.29N) and Galloway (-5.45E).
        'Northern Ireland' => [54.00, 55.25, -8.20, -5.45],
        'Scotland' => [54.95, 59.20, -8.20, -1.90],
    ];

    /**
     * Resolve a region name case-insensitively to its canonical key.
     */
    public static function regionKey(string $region): ?string
    {
        foreach (array_keys(self::REGION_BOXES) as $key) {
            if (strcasecmp($key, trim($region)) === 0) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Fetch named cave entrances in a bounding box, parsed and de-duplicated.
     *
     * @param  array{float, float, float, float}|null  $box  [latMin, latMax, lngMin, lngMax]; null for the whole UK
     * @return list<array{name: string, osm_id: string, lat: float, lng: float, alt: float|null, tags: array<string, string>}>|null null if Overpass failed
     */
    public function fetchEntrances(?array $box, bool $includeUnnamed = false, ?callable $warn = null): ?array
    {
        $payload = $this->overpass($this->areaQuery($box), $warn);

        return $payload === null ? null : $this->parse($payload, $includeUnnamed);
    }

    /**
     * Fetch specific nodes by id (for importing a curated selection).
     *
     * @param  list<string>  $nodeIds
     * @return list<array{name: string, osm_id: string, lat: float, lng: float, alt: float|null, tags: array<string, string>}>|null
     */
    public function fetchNodes(array $nodeIds, ?callable $warn = null): ?array
    {
        $ids = implode(',', array_map('intval', $nodeIds));
        $payload = $this->overpass("[out:json][timeout:60];node(id:{$ids})[\"natural\"=\"cave_entrance\"];out body;", $warn);

        // No same-name de-duplication here: the admin chose each node explicitly.
        return $payload === null ? null : $this->parse($payload, false, dedupe: false);
    }

    /**
     * Annotate entrances with what an import would do, for the curated preview.
     *
     * @param  list<array{name: string, osm_id: string, lat: float, lng: float, alt: float|null, tags: array<string, string>}>  $entries
     * @return list<array<string, mixed>>
     */
    public function describe(array $entries): array
    {
        return array_map(function (array $entry) {
            $linked = Cave::query()->where('osm_node_id', $entry['osm_id'])->first();
            $match = $linked ? null : $this->findExistingCave($entry['name'], $entry['lat'], $entry['lng']);
            $cave = $linked ?? $match;

            return [
                'osm_id' => $entry['osm_id'],
                'osm_url' => self::NODE_URL.$entry['osm_id'],
                'name' => $entry['name'],
                'lat' => $entry['lat'],
                'lng' => $entry['lng'],
                'region' => $this->resolveRegion($entry['lat'], $entry['lng']),
                'access' => $entry['tags']['access'] ?? null,
                'website' => $entry['tags']['website'] ?? null,
                'wikipedia' => $entry['tags']['wikipedia'] ?? null,
                // new: would be created; existing: would be linked only; linked: nothing to do.
                'status' => $linked ? 'linked' : ($match ? 'existing' : 'new'),
                'cave' => $cave ? ['id' => $cave->id, 'name' => $cave->name, 'slug' => $cave->slug] : null,
            ];
        }, $entries);
    }

    /**
     * Import one entrance: create an OSM-sourced cave, or link an existing one.
     *
     * @param  array{name: string, osm_id: string, lat: float, lng: float, alt: float|null, tags: array<string, string>}  $entry
     * @return array{action: 'created'|'linked'|'unchanged', cave: Cave}
     */
    public function import(array $entry): array
    {
        return DB::transaction(function () use ($entry) {
            $osmId = $entry['osm_id'];

            $alreadyLinked = Cave::query()->where('osm_node_id', $osmId)->first();
            if ($alreadyLinked) {
                return ['action' => 'unchanged', 'cave' => $alreadyLinked];
            }

            $existing = $this->findExistingCave($entry['name'], $entry['lat'], $entry['lng']);
            if ($existing) {
                // Link only. A cave already linked to another node is another
                // entrance of the same cave: leave its link alone.
                if (empty($existing->osm_node_id)) {
                    $existing->osm_node_id = $osmId;
                    $existing->save();

                    return ['action' => 'linked', 'cave' => $existing];
                }

                return ['action' => 'unchanged', 'cave' => $existing];
            }

            return ['action' => 'created', 'cave' => $this->createCave($entry)];
        });
    }

    /**
     * The region tag name for a coordinate, or null outside every known area.
     */
    public function resolveRegion(float $lat, float $lng): ?string
    {
        foreach (self::REGION_BOXES as $tagName => [$latMin, $latMax, $lngMin, $lngMax]) {
            if ($lat >= $latMin && $lat <= $latMax && $lng >= $lngMin && $lng <= $lngMax) {
                return $tagName;
            }
        }

        return null;
    }

    /**
     * An existing cave that is the same place: same (normalised) name within
     * SAME_PLACE_KM. Unlike the registry syncs, ownership never lets a name
     * match win at any distance — two OSM caves can share a common name
     * ("Bone Cave", "Hermit's Cave") and be hundreds of miles apart.
     */
    private function findExistingCave(string $name, float $lat, float $lng): ?Cave
    {
        return CaveName::match(Cave::query(), $name)
            ->get()
            ->first(fn (Cave $cave) => $this->distanceKm($lat, $lng, $cave->location_lat, $cave->location_lng) <= self::SAME_PLACE_KM);
    }

    /**
     * @param  array{name: string, osm_id: string, lat: float, lng: float, alt: float|null, tags: array<string, string>}  $entry
     */
    private function createCave(array $entry): Cave
    {
        $name = $entry['name'];
        $osmUrl = self::NODE_URL.$entry['osm_id'];
        $region = $this->resolveRegion($entry['lat'], $entry['lng']);

        $system = CaveSystem::create([
            'name' => $name,
            'slug' => $this->uniqueSlug(Str::slug($name), 'cave_systems'),
            'length' => 0,
            'vertical_range' => 0,
            'references' => '- [OpenStreetMap]('.$osmUrl.')',
        ]);

        $cave = Cave::create([
            'name' => $name,
            'slug' => $this->uniqueSlug('osm_'.Str::slug($name), 'caves'),
            'cave_system_id' => $system->id,
            'registry' => self::REGISTRY,
            'registry_id' => $entry['osm_id'],
            'description' => $this->description($entry),
            'location_name' => $region ?? '',
            'location_country' => 'United Kingdom',
            'location_lat' => $entry['lat'],
            'location_lng' => $entry['lng'],
        ] + ($entry['alt'] !== null ? ['location_alt' => $entry['alt']] : []));
        // Guarded outside $fillable on purpose; set explicitly.
        $cave->osm_node_id = $entry['osm_id'];
        $cave->save();

        $tagIds = [Tag::firstOrCreate(['tag' => 'Cave', 'category' => 'type'], ['type' => 'cave'])->id];
        if ($region !== null) {
            $regionTag = Tag::where('tag', $region)->where('category', 'region')->first();
            if ($regionTag) {
                $tagIds[] = $regionTag->id;
            }
        }
        $cave->tags()->syncWithoutDetaching($tagIds);

        return $cave;
    }

    /**
     * Description for an OSM-sourced cave: a link back to the node, any links
     * OSM holds (website, Wikipedia), and the ODbL attribution.
     *
     * @param  array{name: string, osm_id: string, lat: float, lng: float, alt: float|null, tags: array<string, string>}  $entry
     */
    private function description(array $entry): string
    {
        $lines = ["See [{$entry['name']} on OpenStreetMap](".self::NODE_URL.$entry['osm_id'].').'];

        $website = $entry['tags']['website'] ?? null;
        if (is_string($website) && preg_match('#^https?://#i', $website)) {
            $lines[] = "Website: <{$website}>";
        }

        $wikipedia = $entry['tags']['wikipedia'] ?? null; // "en:Article title"
        if (is_string($wikipedia) && preg_match('/^([a-z-]{2,12}):(.+)$/', $wikipedia, $m)) {
            $lines[] = "Wikipedia: <https://{$m[1]}.wikipedia.org/wiki/".rawurlencode(str_replace(' ', '_', $m[2])).'>';
        }

        return implode("\n\n", $lines)."\n\n".self::ATTRIBUTION;
    }

    /**
     * Run a query against each Overpass endpoint in turn, retrying transient
     * failures. Returns the decoded payload, or null if every attempt failed.
     *
     * Overpass can answer HTTP 200 with an HTML error page, or with JSON whose
     * `remark` reports a runtime error/timeout and an empty (or truncated)
     * `elements` list. Both are failures: treating them as "no caves" would
     * silently import nothing.
     *
     * @return array<string, mixed>|null
     */
    private function overpass(string $query, ?callable $warn): ?array
    {
        foreach (self::OVERPASS_ENDPOINTS as $endpoint) {
            for ($attempt = 1; $attempt <= self::ATTEMPTS_PER_ENDPOINT; ++$attempt) {
                try {
                    $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                        ->timeout(180)
                        ->get($endpoint, ['data' => $query]);

                    $payload = $response->successful() ? $response->json() : null;
                    $remark = is_array($payload) ? (string) ($payload['remark'] ?? '') : '';

                    if (is_array($payload) && is_array($payload['elements'] ?? null) && !str_contains(strtolower($remark), 'error')) {
                        return $payload;
                    }

                    $reason = $remark !== '' ? $remark : 'status '.$response->status();
                } catch (\Exception $e) {
                    $reason = $e->getMessage();
                }

                $message = "Overpass request to {$endpoint} failed (attempt {$attempt}): {$reason}";
                $warn ? $warn($message) : Log::warning($message);

                if ($attempt < self::ATTEMPTS_PER_ENDPOINT) {
                    Sleep::for(self::RETRY_DELAY_SECONDS)->seconds();
                }
            }
        }

        return null;
    }

    /**
     * @param  array{float, float, float, float}|null  $box
     */
    private function areaQuery(?array $box): string
    {
        if ($box === null) {
            return <<<'OVERPASS'
[out:json][timeout:180];
area["ISO3166-1"="GB"][admin_level=2]->.uk;
node["natural"="cave_entrance"](area.uk);
out body;
OVERPASS;
        }

        [$latMin, $latMax, $lngMin, $lngMax] = $box;

        // Overpass bbox order is (south, west, north, east).
        return "[out:json][timeout:120];node[\"natural\"=\"cave_entrance\"]({$latMin},{$lngMin},{$latMax},{$lngMax});out body;";
    }

    /**
     * Parse an Overpass payload into cave entries.
     *
     * Same-named nodes within SAME_CAVE_KM are entrances of one cave: only the
     * first is kept, so a multi-entrance cave isn't imported several times.
     * Same-named nodes further apart are different caves and are all kept.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{name: string, osm_id: string, lat: float, lng: float, alt: float|null, tags: array<string, string>}>
     */
    private function parse(array $payload, bool $includeUnnamed, bool $dedupe = true): array
    {
        $entries = [];
        /** @var array<string, list<array{float, float}>> $seen */
        $seen = [];

        foreach ($payload['elements'] ?? [] as $el) {
            if (($el['type'] ?? null) !== 'node' || !isset($el['lat'], $el['lon'], $el['id'])) {
                continue;
            }

            $tags = array_map('strval', (array) ($el['tags'] ?? []));
            $name = trim($tags['name'] ?? '');

            if ($name === '') {
                if (!$includeUnnamed) {
                    continue;
                }
                $name = 'Cave entrance #'.$el['id'];
            }

            $lat = round((float) $el['lat'], 7);
            $lng = round((float) $el['lon'], 7);

            if ($dedupe) {
                $key = CaveName::normalise($name);
                foreach ($seen[$key] ?? [] as [$seenLat, $seenLng]) {
                    if ($this->distanceKm($lat, $lng, $seenLat, $seenLng) <= self::SAME_CAVE_KM) {
                        continue 2;
                    }
                }
                $seen[$key][] = [$lat, $lng];
            }

            $entries[] = [
                'name' => $name,
                'osm_id' => (string) $el['id'],
                'lat' => $lat,
                'lng' => $lng,
                'alt' => $this->parseElevation($tags['ele'] ?? null),
                'tags' => $tags,
            ];
        }

        return $entries;
    }

    /** Parse an OSM `ele` tag (e.g. "412", "412.5", "412 m") into metres. */
    private function parseElevation(?string $ele): ?float
    {
        if ($ele !== null && preg_match('/-?\d+(\.\d+)?/', $ele, $m)) {
            return (float) $m[0];
        }

        return null;
    }

    private function distanceKm(float $lat1, float $lng1, mixed $lat2, mixed $lng2): float
    {
        if ($lat2 === null || $lng2 === null || (abs((float) $lat2) < 0.0001 && abs((float) $lng2) < 0.0001)) {
            return INF; // unknown location: never "the same place"
        }

        $dLat = deg2rad((float) $lat2 - $lat1);
        $dLng = deg2rad((float) $lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad((float) $lat2)) * sin($dLng / 2) ** 2;

        return 6371.0 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function uniqueSlug(string $base, string $table): string
    {
        $slug = $base !== '' ? $base : 'cave';
        $suffix = 2;

        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            ++$suffix;
        }

        return $slug;
    }
}
