<?php

namespace App\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\SuggestedEdit;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncMcraCaves extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:mcra-caves
                            {--dry-run : Parse the file without inserting data}
                            {--whitelist= : Comma-separated list of names to always import}
                            {--blocklist= : Comma-separated list of names to always skip}
                            {--min-length=0 : Minimum length in meters to import (0 = all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync caves from the Mendip Cave Registry and Archive (MCRA) KML feed';

    /**
     * Google Earth user-agent required by the MCRA KML endpoint.
     */
    private const GOOGLE_EARTH_UA = 'GoogleEarth/7.3.6.9345(Windows;Microsoft Windows (6.2.9200.0);en;kml:2.2;client:Pro;type:default)';

    /**
     * Base URL for the MCRA registry.
     */
    private const MCRA_BASE_URL = 'https://www.mcra.org.uk/registry';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $minLength = (float) $this->option('min-length');
        $whitelistNames = $this->getWhitelist();
        $blocklistNames = $this->getBlocklist();

        $this->info('Fetching MCRA cave placemarks...');

        // The KML feed is split: Caves100 = over 100m, Caves = under 100m
        $placemarks = [];
        foreach (['Caves100', 'Caves'] as $query) {
            $url = self::MCRA_BASE_URL.'/googleEarth/placemarks.php?query='.$query;

            try {
                $response = Http::withHeaders(['User-Agent' => self::GOOGLE_EARTH_UA])->get($url);
                if (!$response->successful()) {
                    $this->error("Failed to download KML from: {$url} (status {$response->status()})");

                    return 1;
                }
            } catch (\Exception $e) {
                $this->error('HTTP request failed: '.$e->getMessage());

                return 1;
            }

            $parsed = $this->parsePlacemarks($response->body());
            if ($parsed === null) {
                $this->error("Failed to parse KML from: {$url}");

                return 1;
            }

            $placemarks = array_merge($placemarks, $parsed);
            $this->line('  Found '.count($parsed)." entries in {$query}.");
        }

        $this->info('Total placemarks found: '.count($placemarks));

        DB::beginTransaction();

        $importedCount = 0;
        $skippedCount = 0;
        $newCaveCount = 0;
        $suggestedEditCount = 0;
        $noOpCount = 0;

        try {
            foreach ($placemarks as $placemark) {
                $name = $placemark['name'];
                $mcraId = $placemark['mcra_id'];
                $lat = $placemark['lat'];
                $lng = $placemark['lng'];
                $kmlDescription = $placemark['description'];

                if (empty($name) || empty($mcraId)) {
                    $this->warn('Skipping placemark with missing name or ID: '.json_encode($placemark));
                    continue;
                }

                // Apply blocklist
                $isBlocklisted = in_array(strtolower($name), array_map('strtolower', $blocklistNames));
                if ($isBlocklisted) {
                    ++$skippedCount;
                    continue;
                }

                $isWhitelisted = in_array(strtolower($name), array_map('strtolower', $whitelistNames));

                // Fetch per-cave details (length, depth, altitude, location name)
                $siteDetails = $this->fetchSiteDetails($mcraId);
                // Brief pause to be respectful to the MCRA server (skipped in testing)
                if (!app()->environment('testing')) {
                    usleep(100000); // 100ms
                }

                $length = (float) ($siteDetails['length'] ?? 0);
                $depth = (float) ($siteDetails['depth'] ?? 0);
                $altitude = (float) ($siteDetails['altitude'] ?? 0);

                // Apply min-length filter (only if we have length data)
                $isLongEnough = $minLength <= 0 || ($length > 0 && $length >= $minLength);

                if (!$isWhitelisted && !$isLongEnough) {
                    ++$skippedCount;
                    continue;
                }

                $this->line("Processing: {$name} <fg=gray>(".($isWhitelisted ? 'Whitelisted' : 'Length: '.$length.' m').')</>');
                ++$importedCount;

                if ($dryRun) {
                    continue;
                }

                // 1. Cave System (default to cave name as system name)
                $systemName = $name;
                $systemSlug = Str::slug($systemName);
                $system = CaveSystem::where('name', $systemName)->first()
                    ?? CaveSystem::where('slug', $systemSlug)->first();

                $systemIsNew = false;
                if (!$system) {
                    $system = CaveSystem::create([
                        'name' => $systemName,
                        'slug' => $this->uniqueSlug($systemSlug, 'cave_systems'),
                        'length' => (int) $length,
                        'vertical_range' => (int) $depth,
                    ]);
                    $systemIsNew = true;
                }

                if ($length > 0 || $depth > 0) {
                    $system->length = max((int) $system->length, (int) $length);
                    $system->vertical_range = max((int) $system->vertical_range, (int) $depth);
                    $system->save();
                }

                // 2. Build references for the cave system
                $mcraLink = self::MCRA_BASE_URL.'/sitedetails.php?id='.$mcraId;
                $systemReferences = ['[MCRA Registry]('.$mcraLink.')'];
                $systemReferences = array_values(array_unique($systemReferences));
                $formattedNewRefs = array_map(fn ($r) => '- '.$r, $systemReferences);

                if ($systemIsNew) {
                    $system->references = implode("\n", $formattedNewRefs);
                    $system->save();
                } else {
                    $existingRefs = !empty($system->references) ? explode("\n", $system->references) : [];
                    $normalizedExisting = array_map(
                        fn ($ref) => strtolower(trim(preg_replace('/^-\s*/', '', $ref))),
                        $existingRefs
                    );
                    $existingRefsLower = strtolower($system->references ?? '');

                    $newRefs = array_filter($systemReferences, function ($ref) use ($normalizedExisting, $existingRefsLower) {
                        $normalizedRef = strtolower(trim($ref));

                        return !in_array($normalizedRef, $normalizedExisting)
                            && !str_contains($existingRefsLower, $normalizedRef);
                    });

                    if (!empty($newRefs)) {
                        $suggestedRefs = array_merge($existingRefs, array_map(fn ($r) => '- '.$r, $newRefs));
                        $suggestedValue = implode("\n", $suggestedRefs);

                        $existingPendingEdit = SuggestedEdit::where('suggestable_type', CaveSystem::class)
                            ->where('suggestable_id', $system->id)
                            ->where('status', 'pending')
                            ->first();

                        if ($existingPendingEdit) {
                            $mergedSuggested = array_merge($existingPendingEdit->suggested_data, ['references' => $suggestedValue]);
                            $mergedOriginal = array_merge($existingPendingEdit->original_data, ['references' => $system->references]);
                            $existingPendingEdit->update([
                                'original_data' => $mergedOriginal,
                                'suggested_data' => $mergedSuggested,
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
                        $this->line("<fg=yellow>  ✏ Suggested references update:</> {$name}");
                        ++$suggestedEditCount;
                    }
                }

                // 3. Build description from KML HTML
                $descriptionText = $this->extractDescriptionText($kmlDescription);
                $mcraLinkMd = '[MCRA Registry]('.$mcraLink.')';
                $descriptionParts = array_filter([$descriptionText, $mcraLinkMd]);
                $description = implode("\n\n", $descriptionParts);

                // 4. Location name
                $locationName = $siteDetails['location_name'] ?? null;

                // 5. Build cave data array
                $caveData = [
                    'description' => $description,
                    'location_name' => $locationName ?: '',
                    'location_country' => 'United Kingdom',
                    'location_lat' => $lat,
                    'location_lng' => $lng,
                    'location_alt' => $altitude > 0 ? $altitude : null,
                ];

                $baseSlug = 'mendip_'.Str::slug($name);

                // Look up existing cave: registry_id first, then name, then slug
                $existingCave = Cave::where('registry', 'mcra')->where('registry_id', $mcraId)->first()
                    ?? Cave::where('name', $name)->first()
                    ?? Cave::where('slug', $baseSlug)->first();

                if ($existingCave) {
                    // Check for differences
                    $coordKeys = ['location_lat', 'location_lng', 'location_alt'];
                    $textKeys = ['description', 'location_name'];
                    $differences = [];

                    foreach ($caveData as $key => $value) {
                        if (in_array($key, $coordKeys)) {
                            $existingRounded = round((float) $existingCave->$key, 5);
                            $newRounded = round((float) $value, 5);
                            if ($existingRounded !== $newRounded) {
                                $differences[$key] = $value;
                            }
                        } elseif (in_array($key, $textKeys)) {
                            $existingText = (string) ($existingCave->$key ?? '');
                            $newText = (string) ($value ?? '');
                            if (!empty($newText) && !$this->textAlreadyContained($newText, $existingText)) {
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

                    // Persist registry tracking so future syncs can match by ID
                    if (empty($existingCave->registry) || empty($existingCave->registry_id)) {
                        $existingCave->registry = 'mcra';
                        $existingCave->registry_id = $mcraId;
                        $existingCave->save();
                    }

                    $cave = $existingCave;
                } else {
                    $cave = Cave::create(array_merge([
                        'name' => $name,
                        'slug' => $this->uniqueSlug($baseSlug, 'caves'),
                        'cave_system_id' => $system->id,
                        'registry' => 'mcra',
                        'registry_id' => $mcraId,
                    ], $caveData));
                    $this->line("<fg=green>  ✚ New cave created:</> {$name}");
                    ++$newCaveCount;
                }

                // 6. Sync tags: Cave type + Mendip region
                // Note: the Curated tag is NOT added here — it is only applied to
                // pre-existing caves via the tag_existing_caves_as_curated migration.
                $tagCave = Tag::where('tag', 'Cave')->where('category', 'type')->firstOrFail();
                $tagMendip = Tag::where('tag', 'Mendip')->where('category', 'region')->firstOrFail();
                $cave->tags()->syncWithoutDetaching([$tagCave->id, $tagMendip->id]);
            }

            if (!$dryRun) {
                DB::commit();
                $this->newLine();
                $this->info("Import completed: {$importedCount} processed, {$skippedCount} skipped.");
                $this->line("  <fg=green>✚ New caves:</> {$newCaveCount}");
                $this->line("  <fg=yellow>✏ Suggested edits:</> {$suggestedEditCount}");
                $this->line("  <fg=blue>⊘ No changes:</> {$noOpCount}");
            } else {
                DB::rollBack();
                $this->info("Dry run completed: {$importedCount} would be imported/updated, {$skippedCount} skipped.");
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error during import: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return 1;
        }

        return 0;
    }

    /**
     * Parse KML placemarks from a KML XML string.
     *
     * Returns an array of associative arrays with keys:
     *   name, mcra_id, lat, lng, description
     *
     * @return array<int, array{name: string, mcra_id: string, lat: float, lng: float, description: string}>|null
     */
    private function parsePlacemarks(string $kmlXml): ?array
    {
        $xml = @simplexml_load_string($kmlXml);
        if ($xml === false) {
            return null;
        }

        // Use local-name() to avoid needing to register the KML namespace
        $placemarkNodes = $xml->xpath('//*[local-name()="Placemark"]');
        if (empty($placemarkNodes)) {
            return [];
        }

        $results = [];
        foreach ($placemarkNodes as $node) {
            // Use XPath with local-name() to avoid default-namespace issues in SimpleXML
            $nameNodes = $node->xpath('*[local-name()="name"]');
            $name = !empty($nameNodes) ? trim((string) $nameNodes[0]) : '';
            if (empty($name)) {
                continue;
            }

            // Extract coordinates: KML Point uses "lng,lat[,alt]"
            $coordNodes = $node->xpath('.//*[local-name()="coordinates"]');
            $coordStr = !empty($coordNodes) ? trim((string) $coordNodes[0]) : '';
            if (empty($coordStr)) {
                continue;
            }
            [$lngStr, $latStr] = explode(',', $coordStr) + [0, 0];
            $lng = round((float) $lngStr, 7);
            $lat = round((float) $latStr, 7);

            // Extract MCRA ID from sitedetails link in the CDATA description
            $descNodes = $node->xpath('*[local-name()="description"]');
            $rawDescription = !empty($descNodes) ? (string) $descNodes[0] : '';
            if (!preg_match('/sitedetails\.php\?id=(\d+)/', $rawDescription, $idMatch)) {
                $this->warn("Could not extract MCRA ID for: {$name}");
                continue;
            }
            $mcraId = $idMatch[1];

            $results[] = [
                'name' => $name,
                'mcra_id' => $mcraId,
                'lat' => $lat,
                'lng' => $lng,
                'description' => $rawDescription,
            ];
        }

        return $results;
    }

    /**
     * Fetch per-cave details from the MCRA site details page.
     *
     * Returns an array with keys: length, depth, altitude, location_name
     *
     * @return array{length: float, depth: float, altitude: float, location_name: string|null}|null
     */
    private function fetchSiteDetails(string $mcraId): ?array
    {
        $url = self::MCRA_BASE_URL.'/sitedetails.php?id='.$mcraId;

        try {
            $response = Http::withHeaders(['User-Agent' => self::GOOGLE_EARTH_UA])->get($url);
            if (!$response->successful()) {
                $this->warn("  Could not fetch site details for ID {$mcraId} (status {$response->status()})");

                return null;
            }
        } catch (\Exception $e) {
            $this->warn("  Failed to fetch site details for ID {$mcraId}: ".$e->getMessage());

            return null;
        }

        $html = $response->body();

        // Extract Length, Depth, Altitude from the summary table
        $length = 0.0;
        $depth = 0.0;
        $altitude = 0.0;
        if (preg_match('/Length:<\/td><td[^>]*>([\d.]+)\s*m/', $html, $m)) {
            $length = (float) $m[1];
        }
        if (preg_match('/Depth:<\/td><td[^>]*>([\d.]+)\s*m/', $html, $m)) {
            $depth = (float) $m[1];
        }
        if (preg_match('/Altitude:<\/td><td[^>]*>([\d.]+)\s*m/', $html, $m)) {
            $altitude = (float) $m[1];
        }

        // Extract location name from the bold paragraph immediately after <h1>
        // Pattern: <p><strong>Location, Area.</strong></p>
        $locationName = null;
        if (preg_match('/<h1>[^<]+<\/h1>.*?<p><strong>([^<]+)<\/strong><\/p>/s', $html, $m)) {
            $locationName = rtrim(trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')), '.');
        }

        return [
            'length' => $length,
            'depth' => $depth,
            'altitude' => $altitude,
            'location_name' => $locationName,
        ];
    }

    /**
     * Extract plain-text description from the MCRA KML CDATA HTML block.
     *
     * The CDATA contains an optional image link, one or more <p> description
     * paragraphs, a "Full Site Details" link paragraph, and a copyright footer.
     * This method strips everything except the description paragraphs.
     */
    private function extractDescriptionText(string $html): string
    {
        if (empty(trim($html))) {
            return '';
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<html><body>'.$html.'</body></html>');
        libxml_clear_errors();

        $paragraphs = $dom->getElementsByTagName('p');
        $parts = [];
        foreach ($paragraphs as $p) {
            $text = trim($p->textContent);
            // Skip the "Full Site Details" link paragraph and the copyright footer
            if (str_contains($text, 'Full Site Details') || str_contains($text, 'Database content Copyright')) {
                continue;
            }
            if (!empty($text)) {
                // Remove trailing "[...more]" truncation marker
                $text = preg_replace('/\s*\[\.\.\.more\]\s*$/', '', $text);
                $text = trim($text);
                if (!empty($text)) {
                    $parts[] = $text;
                }
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Check whether all paragraphs of $newText are already contained in $existingText.
     *
     * This avoids suggesting redundant updates when the MCRA text is already
     * present in a longer Subterra description. Also handles MCRA Registry ID
     * matching regardless of link formatting.
     */
    private function textAlreadyContained(string $newText, string $existingText): bool
    {
        if (empty($existingText)) {
            return false;
        }

        $parts = preg_split('/\n{2,}/', trim($newText));

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if (!str_contains($existingText, $part)) {
                // For MCRA Registry links, match on the numeric ID alone
                if (preg_match('/MCRA Registry/i', $part) && preg_match('/[?&]id=(\d+)/i', $part, $idMatch)) {
                    if (str_contains($existingText, 'id='.$idMatch[1])) {
                        continue;
                    }
                }

                return false;
            }
        }

        return true;
    }

    /**
     * Generate a unique slug in the given table, appending a counter if needed.
     */
    private function uniqueSlug(string $base, string $table): string
    {
        $slug = $base;
        $count = 2;
        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$count++;
        }

        return $slug;
    }

    /**
     * Get the list of whitelisted cave names from the option or file.
     *
     * @return array<string>
     */
    private function getWhitelist(): array
    {
        $whitelist = [];
        $whitelistArg = $this->option('whitelist');
        if (!empty($whitelistArg)) {
            $whitelist = array_map('trim', explode(',', $whitelistArg));
        }

        $filePath = storage_path('app/mcra_whitelist.txt');
        if (file_exists($filePath)) {
            $names = array_map('trim', explode("\n", file_get_contents($filePath)));
            $names = array_filter($names, fn ($n) => !empty($n) && !str_starts_with($n, '#'));
            $whitelist = array_merge($whitelist, array_values($names));
        }

        return $whitelist;
    }

    /**
     * Get the list of blocklisted cave names from the option or file.
     *
     * @return array<string>
     */
    private function getBlocklist(): array
    {
        $blocklist = [];
        $blocklistArg = $this->option('blocklist');
        if (!empty($blocklistArg)) {
            $blocklist = array_map('trim', explode(',', $blocklistArg));
        }

        $filePath = storage_path('app/mcra_blocklist.txt');
        if (file_exists($filePath)) {
            $names = array_map('trim', explode("\n", file_get_contents($filePath)));
            $names = array_filter($names, fn ($n) => !empty($n) && !str_starts_with($n, '#'));
            $blocklist = array_merge($blocklist, array_values($names));
        }

        return $blocklist;
    }
}
