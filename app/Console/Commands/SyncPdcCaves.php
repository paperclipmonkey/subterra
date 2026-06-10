<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\SuggestedEdit;
use App\Models\Tag;
use App\Support\CaveName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncPdcCaves extends Command
{
    protected $signature = 'sync:pdc-caves
                            {--dry-run : Parse without inserting data}
                            {--blocklist= : Comma-separated list of cave names to always skip}';

    protected $description = 'Sync caves from the Peak District Caving (DCA) cave index';

    private const BASE_URL = 'https://peakdistrictcaving.info';

    private const INDEX_URL = 'https://peakdistrictcaving.info/home/the-caves';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $blocklistNames = $this->getBlocklist();

        $this->info('Fetching Peak District Caving index...');

        try {
            $indexResponse = Http::get(self::INDEX_URL);
            if (!$indexResponse->successful()) {
                $this->error('Failed to download PDC cave index (status '.$indexResponse->status().')');

                return 1;
            }
        } catch (\Exception $e) {
            $this->error('HTTP request failed: '.$e->getMessage());

            return 1;
        }

        $regions = $this->parseRegions($indexResponse->body());

        if (empty($regions)) {
            $this->warn('No regions found in the PDC index.');

            return 0;
        }

        $this->info('Found '.count($regions).' regions in the PDC index.');

        DB::beginTransaction();

        $importedCount = 0;
        $skippedCount = 0;
        $newCaveCount = 0;
        $suggestedEditCount = 0;
        $noOpCount = 0;

        try {
            foreach ($regions as $region) {
                $regionName = $region['name'];
                $regionSlug = $region['slug'];

                $this->info("Fetching region: {$regionName}...");

                try {
                    $regionResponse = Http::get(self::BASE_URL.'/home/the-caves/'.$regionSlug);
                    if (!$regionResponse->successful()) {
                        $this->warn("  Could not fetch region '{$regionName}' (status {$regionResponse->status()})");
                        continue;
                    }
                } catch (\Exception $e) {
                    $this->warn("  HTTP error fetching region '{$regionName}': ".$e->getMessage());
                    continue;
                }

                if (!app()->environment('testing')) {
                    usleep(200000); // 200ms — be polite to the PDC server
                }

                $entries = $this->parseRegionPage($regionResponse->body(), $regionName, $regionSlug);

                foreach ($entries as $entry) {
                    $name = $entry['name'];
                    $caveSlug = $entry['cave_slug'];
                    $regionSlugEntry = $entry['region_slug'];
                    $length = $entry['length'];
                    $depth = $entry['depth'];

                    if (in_array(strtolower($name), array_map('strtolower', $blocklistNames))) {
                        $this->line("<fg=gray>  ⊘ Skipped (blocklist):</> {$name}");
                        ++$skippedCount;
                        continue;
                    }

                    // Fetch detail page for coordinates and access info
                    $detail = $this->fetchCaveDetail($regionSlugEntry, $caveSlug);

                    if (!app()->environment('testing')) {
                        usleep(200000);
                    }

                    $lat = $detail['lat'];
                    $lng = $detail['lng'];
                    $accessInfo = $detail['access_info'];

                    // A 0,0 location means we couldn't extract GPS coordinates.
                    // Don't create a brand-new cave for it — but still process
                    // caves that already exist so their references/tags stay current.
                    $registryId = $regionSlugEntry.'/'.$caveSlug;
                    $baseSlug = 'pdc_'.$regionSlugEntry.'_'.$caveSlug;
                    $existingCave = Cave::where('registry', 'pdc')->where('registry_id', $registryId)->first()
                        ?? CaveName::findCave($name)
                        ?? Cave::where('slug', $baseSlug)->first();

                    if (!$existingCave && round($lat, 5) === 0.0 && round($lng, 5) === 0.0) {
                        $this->line("<fg=gray>  ⊘ Skipped (no GPS):</> {$name}");
                        ++$skippedCount;
                        continue;
                    }

                    $this->line("Processing: {$name} <fg=gray>({$regionName})</>");
                    ++$importedCount;

                    if ($dryRun) {
                        continue;
                    }

                    // -----------------------------------------------------------------
                    // 1. Cave System
                    // -----------------------------------------------------------------
                    $systemName = $name;
                    $systemSlug = Str::slug($systemName);
                    $system = CaveName::findSystem($systemName)
                        ?? CaveSystem::where('slug', $systemSlug)->first();

                    $systemIsNew = false;
                    if (!$system) {
                        $system = CaveSystem::create([
                            'name' => $systemName,
                            'slug' => $this->uniqueSlug($systemSlug, 'cave_systems'),
                            'length' => $length ?? 0,
                            'vertical_range' => $depth ?? 0,
                        ]);
                        $systemIsNew = true;
                    }

                    // -----------------------------------------------------------------
                    // 2. Registry reference on the cave system
                    // -----------------------------------------------------------------
                    $cavePageUrl = self::BASE_URL.'/home/the-caves/'.$regionSlugEntry.'/'.$caveSlug;
                    $registryLinkMd = '- [Peak District Caving page]('.$cavePageUrl.')';

                    if ($systemIsNew) {
                        $system->references = $registryLinkMd;
                        $system->save();
                    } else {
                        $existingRefsLower = strtolower($system->references ?? '');
                        if (!str_contains($existingRefsLower, strtolower($cavePageUrl))) {
                            $existingRefs = !empty($system->references)
                                ? explode("\n", $system->references)
                                : [];

                            $suggestedValue = implode("\n", array_merge($existingRefs, [$registryLinkMd]));

                            $existingPendingEdit = SuggestedEdit::where('suggestable_type', CaveSystem::class)
                                ->where('suggestable_id', $system->id)
                                ->where('status', 'pending')
                                ->first();

                            if ($existingPendingEdit) {
                                $existingPendingEdit->update([
                                    'original_data' => array_merge($existingPendingEdit->original_data, ['references' => $system->references]),
                                    'suggested_data' => array_merge($existingPendingEdit->suggested_data, ['references' => $suggestedValue]),
                                ]);
                            } else {
                                SuggestedEdit::create([
                                    'user_id' => null,
                                    'suggestable_type' => CaveSystem::class,
                                    'suggestable_id' => $system->id,
                                    'original_data' => ['references' => $system->references],
                                    'suggested_data' => ['references' => $suggestedValue],
                                    'status' => 'pending',
                                ]);
                            }
                            ++$suggestedEditCount;
                        }
                    }

                    // -----------------------------------------------------------------
                    // 3. Cave data
                    // -----------------------------------------------------------------
                    $pdcLink = '[Peak District Caving page for '.$name.']('.$cavePageUrl.')';

                    // Use the scraped access info if available, otherwise fall back to the PDC link
                    $accessInfoValue = !empty($accessInfo)
                        ? $accessInfo."\n\nFor more information see ".$pdcLink.'.'
                        : 'For more information see '.$pdcLink.'.';

                    $caveData = [
                        'description' => 'For more information see '.$pdcLink.'.',
                        'access_info' => $accessInfoValue,
                        'location_name' => $regionName,
                        'location_country' => 'United Kingdom',
                        'location_lat' => $lat,
                        'location_lng' => $lng,
                    ];

                    if ($existingCave) {
                        $coordKeys = ['location_lat', 'location_lng'];
                        $textKeys = ['location_name', 'description', 'access_info'];
                        $differences = [];

                        foreach ($caveData as $key => $value) {
                            if ($value === null) {
                                continue;
                            }
                            if (in_array($key, $coordKeys)) {
                                $existingRounded = round((float) $existingCave->$key, 4);
                                $newRounded = round((float) $value, 4);
                                if ($newRounded != 0 && $existingRounded !== $newRounded) {
                                    $differences[$key] = $value;
                                }
                            } elseif (in_array($key, $textKeys)) {
                                if (!empty($value) && (string) $existingCave->$key !== (string) $value) {
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
                                    ? round((float) $val, 4)
                                    : $val;
                            }

                            $existingPendingEdit = SuggestedEdit::where('suggestable_type', Cave::class)
                                ->where('suggestable_id', $existingCave->id)
                                ->where('status', 'pending')
                                ->first();

                            if ($existingPendingEdit) {
                                $existingPendingEdit->update([
                                    'original_data' => $originalData,
                                    'suggested_data' => $differences,
                                ]);
                                $this->line("<fg=yellow>  ✏ Updated suggested edit:</> {$name} <fg=gray>[".implode(', ', array_keys($differences)).']</>');
                            } else {
                                SuggestedEdit::create([
                                    'user_id' => null,
                                    'suggestable_type' => Cave::class,
                                    'suggestable_id' => $existingCave->id,
                                    'original_data' => $originalData,
                                    'suggested_data' => $differences,
                                    'status' => 'pending',
                                ]);
                                $this->line("<fg=yellow>  ✏ Created suggested edit:</> {$name} <fg=gray>[".implode(', ', array_keys($differences)).']</>');
                            }
                            ++$suggestedEditCount;
                        } else {
                            $this->line("<fg=blue>  ⊘ No changes:</> {$name}");
                            ++$noOpCount;
                        }

                        if (empty($existingCave->registry) || empty($existingCave->registry_id)) {
                            $existingCave->registry = 'pdc';
                            $existingCave->registry_id = $registryId;
                            $existingCave->save();
                        }

                        $cave = $existingCave;
                    } else {
                        $cave = Cave::create(array_merge(
                            array_filter($caveData, fn ($v) => $v !== null),
                            [
                                'name' => $name,
                                'slug' => $this->uniqueSlug($baseSlug, 'caves'),
                                'cave_system_id' => $system->id,
                                'registry' => 'pdc',
                                'registry_id' => $registryId,
                            ]
                        ));
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
                    $regionTag = Tag::firstOrCreate(
                        ['tag' => 'Peak District', 'category' => 'region'],
                        ['type' => 'cave']
                    );

                    $cave->tags()->syncWithoutDetaching([$tagCave->id, $regionTag->id]);
                }
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
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error during sync: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return 1;
        }

        return 0;
    }

    /**
     * Parse the PDC main index page and return an array of region entries.
     *
     * @return array<int, array{name: string, slug: string}>
     */
    private function parseRegions(string $html): array
    {
        // Region links are <a href="/home/the-caves/{slug}">Region Name</a>
        preg_match_all('/<a\s[^>]*href="\/home\/the-caves\/([a-z0-9][a-z0-9\-]*)"[^>]*>([^<]+)<\/a>/i', $html, $matches, PREG_SET_ORDER);

        $regions = [];
        $seen = [];

        foreach ($matches as $match) {
            $slug = trim($match[1]);
            $name = html_entity_decode(trim($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Exclude non-region links (search, hydrology, surveys, etc.)
            $excluded = ['search', 'hydrology', 'topos', 'surveys', 'guides', 'audits', 'alderley-edge'];
            // alderley-edge is in Cheshire, not Peak District — keep it as it is on the site

            if (empty($slug) || empty($name) || isset($seen[$slug])) {
                continue;
            }

            $seen[$slug] = true;
            $regions[] = ['name' => $name, 'slug' => $slug];
        }

        return $regions;
    }

    /**
     * Parse a PDC region page and return cave entries with name, slugs, length and depth.
     *
     * @return array<int, array{name: string, cave_slug: string, region_slug: string, length: int|null, depth: int|null}>
     */
    private function parseRegionPage(string $html, string $regionName, string $regionSlug): array
    {
        // Cave rows are in <table id="entrance-table">
        // Each row: <td><a href="/home/the-caves/{region}/{cave-slug}">{Name}</a></td>
        //           <td class="dim-m hide">{length}</td>
        //           <td class="dim-m hide">{depth}</td>
        preg_match_all(
            '/<tr>\s*<td><a\s[^>]*href="\/home\/the-caves\/[^\/]+\/([a-z0-9][a-z0-9\-]*)"[^>]*>([^<]+)<\/a><\/td>\s*<td[^>]*>([\d]*)<\/td>\s*<td[^>]*>([\d]*)<\/td>/is',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        $entries = [];
        foreach ($matches as $match) {
            $caveSlug = trim($match[1]);
            $name = html_entity_decode(trim($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $lengthRaw = trim($match[3]);
            $depthRaw = trim($match[4]);

            if (empty($caveSlug) || empty($name)) {
                continue;
            }

            $entries[] = [
                'name' => $name,
                'cave_slug' => $caveSlug,
                'region_slug' => $regionSlug,
                'length' => $lengthRaw !== '' ? (int) $lengthRaw : null,
                'depth' => $depthRaw !== '' ? (int) $depthRaw : null,
            ];
        }

        return $entries;
    }

    /**
     * Fetch a cave detail page and extract lat/lng coordinates and access info.
     *
     * @return array{lat: float, lng: float, access_info: string}
     */
    private function fetchCaveDetail(string $regionSlug, string $caveSlug): array
    {
        $url = self::BASE_URL.'/home/the-caves/'.$regionSlug.'/'.$caveSlug;

        try {
            $response = Http::get($url);
            if (!$response->successful()) {
                $this->warn("  Could not fetch detail page for '{$caveSlug}' (status {$response->status()})");

                return ['lat' => 0.0, 'lng' => 0.0, 'access_info' => ''];
            }
        } catch (\Exception $e) {
            $this->warn("  HTTP error fetching '{$caveSlug}': ".$e->getMessage());

            return ['lat' => 0.0, 'lng' => 0.0, 'access_info' => ''];
        }

        return $this->parseDetail($response->body());
    }

    /**
     * Parse a cave detail page HTML and extract lat/lng and access info.
     *
     * @return array{lat: float, lng: float, access_info: string}
     */
    private function parseDetail(string $html): array
    {
        // Coordinates are in RDFa microdata:
        // <span property="geo:lat">53.3409</span> <span property="geo:long">-1.8221</span>
        $lat = 0.0;
        $lng = 0.0;

        if (preg_match('/<span\s[^>]*property="geo:lat"[^>]*>([\d.\-]+)<\/span>/i', $html, $latMatch)) {
            $lat = (float) $latMatch[1];
        }
        if (preg_match('/<span\s[^>]*property="geo:long"[^>]*>([\d.\-]+)<\/span>/i', $html, $lngMatch)) {
            $lng = (float) $lngMatch[1];
        }

        // Access info is in <section class="md access_description"><h3>Access</h3><p>...</p>
        $accessInfo = '';
        if (preg_match('/<section[^>]*class="[^"]*access_description[^"]*"[^>]*>(.*?)<\/section>/is', $html, $accessMatch)) {
            // Strip HTML tags and decode entities
            $accessInfo = trim(strip_tags(html_entity_decode($accessMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            // Remove the heading "Access" from the start
            $accessInfo = preg_replace('/^Access\s*/i', '', $accessInfo);
            $accessInfo = trim($accessInfo);
        }

        return ['lat' => $lat, 'lng' => $lng, 'access_info' => $accessInfo];
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
