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

class SyncCnccCaves extends Command
{
    protected $signature = 'sync:cncc-caves
                            {--dry-run : Parse without inserting data}
                            {--blocklist= : Comma-separated list of cave names to always skip}';

    protected $description = 'Sync caves from the CNCC (Council for Northern Caving Community) cave index';

    private const BASE_URL = 'https://cncc.org.uk';

    private const INDEX_URL = 'https://cncc.org.uk/caving/caves/?keyword=&sort=cave';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $blocklistNames = $this->getBlocklist();

        $this->info('Fetching CNCC cave index...');

        try {
            $indexResponse = Http::get(self::INDEX_URL);
            if (!$indexResponse->successful()) {
                $this->error('Failed to download CNCC cave index (status '.$indexResponse->status().')');

                return 1;
            }
        } catch (\Exception $e) {
            $this->error('HTTP request failed: '.$e->getMessage());

            return 1;
        }

        $entries = $this->parseIndex($indexResponse->body());

        if (empty($entries)) {
            $this->warn('No cave entries found in the CNCC index.');

            return 0;
        }

        $this->info('Found '.count($entries).' caves in the CNCC index.');

        DB::beginTransaction();

        $importedCount = 0;
        $skippedCount = 0;
        $newCaveCount = 0;
        $suggestedEditCount = 0;
        $noOpCount = 0;

        try {
            foreach ($entries as $entry) {
                $name = $entry['name'];
                $slug = $entry['slug'];
                $region = $entry['region'];

                if (in_array(strtolower($name), array_map('strtolower', $blocklistNames))) {
                    $this->line("<fg=gray>  ⊘ Skipped (blocklist):</> {$name}");
                    ++$skippedCount;
                    continue;
                }

                // Fetch cave detail page for location data
                $detail = $this->fetchCaveDetail($slug);

                if (!app()->environment('testing')) {
                    usleep(150000); // 150ms — be polite to the CNCC server
                }

                $lat = $detail['lat'];
                $lng = $detail['lng'];

                // A 0,0 location means we couldn't extract GPS coordinates. Don't
                // create a brand-new cave for it — but still process caves that
                // already exist so their references/tags stay up to date.
                $baseSlug = 'cncc_'.Str::slug($name);
                $existingCave = Cave::where('registry', 'cncc')->where('registry_id', $slug)->first()
                    ?? CaveName::findCaveForRegistry($name, $baseSlug, 'cncc', $lat, $lng);

                if (!$existingCave && round($lat, 5) === 0.0 && round($lng, 5) === 0.0) {
                    $this->line("<fg=gray>  ⊘ Skipped (no GPS):</> {$name}");
                    ++$skippedCount;
                    continue;
                }

                $this->line("Processing: {$name} <fg=gray>({$region})</>");
                ++$importedCount;

                if ($dryRun) {
                    continue;
                }

                // -----------------------------------------------------------------
                // 1. Cave System
                // -----------------------------------------------------------------
                $systemName = $name;
                $systemSlug = Str::slug($systemName);
                $system = CaveName::findSystemForRegistry($systemName, $systemSlug, 'cncc', $lat, $lng);

                $systemIsNew = false;
                if (!$system) {
                    $system = CaveSystem::create([
                        'name' => $systemName,
                        'slug' => $this->uniqueSlug($systemSlug, 'cave_systems'),
                        'length' => 0,
                        'vertical_range' => 0,
                    ]);
                    $systemIsNew = true;
                }

                // -----------------------------------------------------------------
                // 2. Registry reference on the cave system
                // -----------------------------------------------------------------
                $cavePageUrl = self::BASE_URL.'/cave/'.$slug;
                $registryLinkMd = '- [CNCC Cave Page]('.$cavePageUrl.')';

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
                $cnccLink = '[CNCC page for '.$name.']('.$cavePageUrl.')';

                $caveData = [
                    'description' => 'For more information see '.$cnccLink.'.',
                    'access_info' => 'For more information see '.$cnccLink.'.',
                    'location_name' => $region,
                    'location_country' => 'United Kingdom',
                    'location_lat' => $lat,
                    'location_lng' => $lng,
                ];

                if ($existingCave) {
                    $coordKeys = ['location_lat', 'location_lng'];
                    $textKeys = ['location_name', 'description', 'access_info'];
                    $differences = [];

                    foreach ($caveData as $key => $value) {
                        if (in_array($key, $coordKeys)) {
                            $existingRounded = round((float) $existingCave->$key, 5);
                            $newRounded = round((float) $value, 5);
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
                                ? round((float) $val, 5)
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
                        $existingCave->registry = 'cncc';
                        $existingCave->registry_id = $slug;
                        $existingCave->save();
                    }

                    $cave = $existingCave;
                } else {
                    $cave = Cave::create(array_merge([
                        'name' => $name,
                        'slug' => $this->uniqueSlug($baseSlug, 'caves'),
                        'cave_system_id' => $system->id,
                        'registry' => 'cncc',
                        'registry_id' => $slug,
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
                $regionTag = Tag::firstOrCreate(
                    ['tag' => 'Northern', 'category' => 'region'],
                    ['type' => 'cave']
                );
                $cave->tags()->syncWithoutDetaching([$tagCave->id, $regionTag->id]);
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
     * Parse the CNCC cave index HTML and return an array of cave entries.
     *
     * Each entry has: name, slug, region.
     *
     * @return array<int, array{name: string, slug: string, region: string}>
     */
    private function parseIndex(string $html): array
    {
        // Extract table rows containing cave entries
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $rowMatches);

        $entries = [];
        foreach ($rowMatches[1] as $row) {
            // Extract cave slug and name from the link
            if (!preg_match('/<a[^>]+href="cave\/([^"]+)"[^>]*>([^<]+)</i', $row, $linkMatch)) {
                continue;
            }

            $slug = trim($linkMatch[1]);
            $name = html_entity_decode(trim($linkMatch[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (empty($slug) || empty($name)) {
                continue;
            }

            // Extract region from the <span> element e.g. "(Leck Fell)"
            $region = '';
            if (preg_match('/<span[^>]*>\(([^)]+)\)<\/span>/i', $row, $spanMatch)) {
                $region = html_entity_decode(trim($spanMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $entries[] = [
                'name' => $name,
                'slug' => $slug,
                'region' => $region,
            ];
        }

        return $entries;
    }

    /**
     * Fetch a cave detail page and extract lat/lng coordinates.
     *
     * @return array{lat: float, lng: float}
     */
    private function fetchCaveDetail(string $slug): array
    {
        $url = self::BASE_URL.'/cave/'.$slug;

        try {
            $response = Http::get($url);
            if (!$response->successful()) {
                $this->warn("  Could not fetch detail page for '{$slug}' (status {$response->status()})");

                return ['lat' => 0.0, 'lng' => 0.0];
            }
        } catch (\Exception $e) {
            $this->warn("  HTTP error fetching '{$slug}': ".$e->getMessage());

            return ['lat' => 0.0, 'lng' => 0.0];
        }

        return $this->parseDetail($response->body());
    }

    /**
     * Parse a cave detail page HTML and extract lat/lng.
     *
     * @return array{lat: float, lng: float}
     */
    private function parseDetail(string $html): array
    {
        // Coordinates appear as "54.175453..., -2.346496..." in the location section
        if (preg_match('/(\d{2,3}\.\d{4,}),\s*(-?\d{1,3}\.\d{4,})/', $html, $coordMatch)) {
            return [
                'lat' => (float) $coordMatch[1],
                'lng' => (float) $coordMatch[2],
            ];
        }

        return ['lat' => 0.0, 'lng' => 0.0];
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
