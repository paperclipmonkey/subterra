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

abstract class SyncCaveRegistryBaseCommand extends Command
{
    /**
     * Google Earth user-agent required by the KML endpoint.
     */
    protected const GOOGLE_EARTH_UA = 'GoogleEarth/7.3.6.9345(Windows;Microsoft Windows (6.2.9200.0);en;kml:2.2;client:Pro;type:default)';

    // -----------------------------------------------------------------------
    // Abstract config — subclasses must implement these
    // -----------------------------------------------------------------------

    /** Short identifier stored in the cave.registry column, e.g. 'mcra', 'fod', 'gsg'. */
    abstract protected function registryId(): string;

    /** Base URL of the registry with no trailing slash. */
    abstract protected function baseUrl(): string;

    /**
     * Full URLs of KML feeds to fetch (all placemarks will be merged).
     *
     * @return array<string>
     */
    abstract protected function kmlUrls(): array;

    /** Default region tag name applied to every imported cave, e.g. 'Mendip'. */
    abstract protected function defaultRegionTagName(): string;

    /** Prefix prepended when building cave slugs, e.g. 'mendip_'. */
    abstract protected function slugPrefix(): string;

    /** Filename under storage/app/ for the blocklist (one name per line). */
    abstract protected function blocklistFilename(): string;

    /**
     * Filename under storage/app/ for the whitelist (one name per line).
     * Whitelisted caves bypass the --min-length filter and are always imported.
     * An empty / missing file means no whitelist is applied.
     */
    abstract protected function whitelistFilename(): string;

    /** Human-readable label used in generated registry links, e.g. 'MCRA Registry'. */
    abstract protected function registryLinkLabel(): string;

    // -----------------------------------------------------------------------
    // Optional overrides
    // -----------------------------------------------------------------------

    /**
     * HTML label for the vertical-range / depth cell on site-details pages.
     * Override to 'Vert. Range:' for registries that differ from the default.
     */
    protected function depthFieldLabel(): string
    {
        return 'Depth:';
    }

    /**
     * Resolve the region tag(s) for a cave given its location name.
     *
     * Returns an array of Tag models to apply. Override to implement sub-region
     * logic (e.g. Portland caves within the MCRA dataset).
     *
     * @return array<Tag>
     */
    protected function resolveRegionTags(string $locationName, Tag $defaultRegionTag): array
    {
        return [$defaultRegionTag];
    }

    // -----------------------------------------------------------------------
    // Entry point
    // -----------------------------------------------------------------------

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $minLength = (float) $this->option('min-length');
        $blocklistNames = $this->getBlocklist();
        $whitelistNames = $this->getWhitelist();

        $registryId = $this->registryId();
        $baseUrl = $this->baseUrl();

        $this->info("Fetching {$registryId} cave placemarks...");

        $placemarks = [];
        foreach ($this->kmlUrls() as $url) {
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
            $this->line('  Found '.count($parsed)." entries in {$url}.");
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
                $registryEntryId = $placemark['registry_id'];
                $lat = $placemark['lat'];
                $lng = $placemark['lng'];
                $kmlDescription = $placemark['description'];

                if (empty($name) || empty($registryEntryId)) {
                    $this->warn('Skipping placemark with missing name or ID: '.json_encode($placemark));
                    continue;
                }

                // Blocklist always wins
                if (in_array(strtolower($name), array_map('strtolower', $blocklistNames))) {
                    ++$skippedCount;
                    continue;
                }

                // Fetch per-cave details (length, depth, altitude, location name)
                $siteDetails = $this->fetchSiteDetails($registryEntryId);
                if (!app()->environment('testing')) {
                    usleep(100000); // 100ms — be polite to the registry server
                }

                $length = (float) ($siteDetails['length'] ?? 0);
                $depth = (float) ($siteDetails['depth'] ?? 0);
                $altitude = (float) ($siteDetails['altitude'] ?? 0);

                // Whitelisted caves bypass min-length; everything else must pass the filter
                $isInWhitelist = !empty($whitelistNames)
                    && in_array(strtolower($name), array_map('strtolower', $whitelistNames));
                $isLongEnough = $minLength <= 0 || ($length > 0 && $length >= $minLength);

                if (!$isInWhitelist && !$isLongEnough) {
                    ++$skippedCount;
                    continue;
                }

                $this->line("Processing: {$name} <fg=gray>(Length: {$length} m)</>");
                ++$importedCount;

                if ($dryRun) {
                    continue;
                }

                // -----------------------------------------------------------------
                // 1. Cave System (defaults to the cave name)
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

                // -----------------------------------------------------------------
                // 2. Registry references on the cave system
                // -----------------------------------------------------------------
                $registryLink = $baseUrl.'/sitedetails.php?id='.$registryEntryId;
                $linkLabel = $this->registryLinkLabel();
                $systemReferences = ['['.$linkLabel.']('.$registryLink.')'];
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

                // -----------------------------------------------------------------
                // 3. Build cave data
                // -----------------------------------------------------------------
                $locationName = $siteDetails['location_name'] ?? null;
                $descriptionText = $this->extractDescriptionText($kmlDescription);
                $registryLinkMd = '['.$linkLabel.']('.$registryLink.')';
                $description = implode("\n\n", array_filter([$descriptionText, $registryLinkMd]));

                $caveData = [
                    'description' => $description,
                    'location_name' => $locationName ?: '',
                    'location_country' => 'United Kingdom',
                    'location_lat' => $lat,
                    'location_lng' => $lng,
                    'location_alt' => $altitude > 0 ? $altitude : null,
                ];

                $baseSlug = $this->slugPrefix().Str::slug($name);

                $existingCave = Cave::where('registry', $registryId)->where('registry_id', $registryEntryId)->first()
                    ?? CaveName::findCave($name)
                    ?? Cave::where('slug', $baseSlug)->first();

                if ($existingCave) {
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
                            $cleanExistingText = $this->stripBidiChars($existingText);
                            if (!empty($newText) && (
                                !$this->textAlreadyContained($newText, $cleanExistingText) ||
                                $cleanExistingText !== $existingText
                            )) {
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
                        $existingCave->registry = $registryId;
                        $existingCave->registry_id = $registryEntryId;
                        $existingCave->save();
                    }

                    $cave = $existingCave;
                } else {
                    $cave = Cave::create(array_merge([
                        'name' => $name,
                        'slug' => $this->uniqueSlug($baseSlug, 'caves'),
                        'cave_system_id' => $system->id,
                        'registry' => $registryId,
                        'registry_id' => $registryEntryId,
                    ], $caveData));
                    $this->line("<fg=green>  ✚ New cave created:</> {$name}");
                    ++$newCaveCount;
                }

                // -----------------------------------------------------------------
                // 4. Sync tags
                // -----------------------------------------------------------------
                $tagCave = Tag::where('tag', 'Cave')->where('category', 'type')->firstOrFail();
                $defaultRegionTag = Tag::where('tag', $this->defaultRegionTagName())->where('category', 'region')->firstOrFail();
                $regionTags = $this->resolveRegionTags($locationName ?? '', $defaultRegionTag);

                $tagIds = array_merge(
                    [$tagCave->id],
                    array_map(fn ($t) => $t->id, $regionTags)
                );
                $cave->tags()->syncWithoutDetaching(array_unique($tagIds));
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

    // -----------------------------------------------------------------------
    // Shared private helpers
    // -----------------------------------------------------------------------

    /**
     * Parse KML placemarks from a KML XML string.
     *
     * Returns an array of associative arrays with keys:
     *   name, registry_id, lat, lng, description
     *
     * @return array<int, array{name: string, registry_id: string, lat: float, lng: float, description: string}>|null
     */
    private function parsePlacemarks(string $kmlXml): ?array
    {
        $xml = @simplexml_load_string($kmlXml);
        if ($xml === false) {
            return null;
        }

        $placemarkNodes = $xml->xpath('//*[local-name()="Placemark"]');
        if (empty($placemarkNodes)) {
            return [];
        }

        $results = [];
        foreach ($placemarkNodes as $node) {
            $nameNodes = $node->xpath('*[local-name()="name"]');
            $name = !empty($nameNodes) ? trim((string) $nameNodes[0]) : '';
            if (empty($name)) {
                continue;
            }

            $coordNodes = $node->xpath('.//*[local-name()="coordinates"]');
            $coordStr = !empty($coordNodes) ? trim((string) $coordNodes[0]) : '';
            if (empty($coordStr)) {
                continue;
            }
            [$lngStr, $latStr] = explode(',', $coordStr) + [0, 0];
            $lng = round((float) $lngStr, 7);
            $lat = round((float) $latStr, 7);

            $descNodes = $node->xpath('*[local-name()="description"]');
            $rawDescription = !empty($descNodes) ? (string) $descNodes[0] : '';
            if (!preg_match('/sitedetails\.php\?id=(\d+)/', $rawDescription, $idMatch)) {
                $this->warn("Could not extract registry ID for: {$name}");
                continue;
            }
            $registryId = $idMatch[1];

            $results[] = [
                'name' => $name,
                'registry_id' => $registryId,
                'lat' => $lat,
                'lng' => $lng,
                'description' => $rawDescription,
            ];
        }

        return $results;
    }

    /**
     * Fetch per-cave details from the registry site-details page.
     *
     * @return array{length: float, depth: float, altitude: float, location_name: string|null}|null
     */
    private function fetchSiteDetails(string $registryEntryId): ?array
    {
        $url = $this->baseUrl().'/sitedetails.php?id='.$registryEntryId;

        try {
            $response = Http::withHeaders(['User-Agent' => self::GOOGLE_EARTH_UA])->get($url);
            if (!$response->successful()) {
                $this->warn("  Could not fetch site details for ID {$registryEntryId} (status {$response->status()})");

                return null;
            }
        } catch (\Exception $e) {
            $this->warn("  Failed to fetch site details for ID {$registryEntryId}: ".$e->getMessage());

            return null;
        }

        $html = $response->body();

        $length = 0.0;
        $depth = 0.0;
        $altitude = 0.0;

        if (preg_match('/Length:<\/td><td[^>]*>([\d.]+)\s*m/', $html, $m)) {
            $length = (float) $m[1];
        }

        $depthLabel = preg_quote($this->depthFieldLabel(), '/');
        if (preg_match('/'.$depthLabel.'<\/td><td[^>]*>([\d.]+)\s*m/', $html, $m)) {
            $depth = (float) $m[1];
        }

        if (preg_match('/Altitude:<\/td><td[^>]*>([\d.]+)\s*m/', $html, $m)) {
            $altitude = (float) $m[1];
        }

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
     * Extract plain-text description from the KML CDATA HTML block.
     */
    private function extractDescriptionText(string $html): string
    {
        if (empty(trim($html))) {
            return '';
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>'.$html.'</body></html>');
        libxml_clear_errors();

        $paragraphs = $dom->getElementsByTagName('p');
        $parts = [];
        foreach ($paragraphs as $p) {
            $text = $this->stripBidiChars(trim($p->textContent));
            $text = trim($text);
            if (str_contains($text, 'Full Site Details') || str_contains($text, 'Database content Copyright')) {
                continue;
            }
            if (!empty($text)) {
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
                // For registry links, match on the numeric sitedetails ID alone
                // regardless of how the link label is formatted.
                if (preg_match('/sitedetails\.php\?id=(\d+)/i', $part, $idMatch)) {
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
     * Strip Unicode bidirectional control characters from a string.
     *
     * Some registries embed invisible bidi chars (e.g. U+202D LEFT-TO-RIGHT OVERRIDE,
     * U+202C POP DIRECTIONAL FORMATTING) that corrupt stored text when bytes are later
     * interpreted as Latin-1.
     */
    private function stripBidiChars(string $text): string
    {
        return (string) preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $text);
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
     * Get the list of blocklisted cave names.
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

        $filePath = storage_path('app/'.$this->blocklistFilename());
        if (file_exists($filePath)) {
            $names = array_map('trim', explode("\n", file_get_contents($filePath)));
            $names = array_filter($names, fn ($n) => !empty($n) && !str_starts_with($n, '#'));
            $blocklist = array_merge($blocklist, array_values($names));
        }

        return $blocklist;
    }

    /**
     * Get the list of whitelisted cave names.
     * Whitelisted caves bypass the --min-length filter.
     *
     * @return array<string>
     */
    private function getWhitelist(): array
    {
        $filePath = storage_path('app/'.$this->whitelistFilename());
        if (!file_exists($filePath)) {
            return [];
        }

        $names = array_map('trim', explode("\n", file_get_contents($filePath)));

        return array_values(array_filter($names, fn ($n) => !empty($n) && !str_starts_with($n, '#')));
    }
}
