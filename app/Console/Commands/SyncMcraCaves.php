<?php

namespace App\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncMcraCaves extends Command
{
    private const MCRA_FEED_URL = 'https://www.mcra.org.uk/registry/browse.php?cv=cave';

    private array $detailPageCache = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:mcra-caves
                            {--dry-run : Parse the file without inserting data} 
                            {--whitelist= : Comma-separated list of names to always import} 
                            {--blocklist= : Comma-separated list of names to always skip}
                            {--min-length=0 : Minimum length in meters to import}
                            {--skip-unknown-access : Skip entries where access is explicitly unknown}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync caves from MCRA registry';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $minLength = (float) $this->option('min-length');
        $skipUnknownAccess = (bool) $this->option('skip-unknown-access');
        $whitelistNames = $this->getWhitelist();
        $blocklistNames = $this->getBlocklist();

        $this->info('Fetching MCRA data...');
        $url = self::MCRA_FEED_URL;

        try {
            $response = Http::get($url);
            if (!$response->successful()) {
                $this->error("Failed to download browse feed from: {$url}");

                return 1;
            }
        } catch (\Exception $e) {
            $this->error('HTTP Request failed: '.$e->getMessage());

            return 1;
        }

        $entries = $this->parseBrowseHtmlEntries($response->body(), $url, true);

        if (empty($entries)) {
            $this->warn('No cave records found in browse feed.');

            return 0;
        }

        $this->info('Found '.count($entries).' entries in feed.');

        DB::beginTransaction();

        $importedCount = 0;
        $skippedCount = 0;
        $newCaveCount = 0;
        $suggestedEditCount = 0;
        $noOpCount = 0;

        try {
            foreach ($entries as $entry) {
                $name = $this->extractFirstText($entry, ['Name', 'name', 'Title', 'title']);
                if (empty($name)) {
                    continue;
                }

                $length = $this->extractFirstFloat($entry, ['len', 'length', 'Length', 'LEN']);
                $depth = $this->extractFirstFloat($entry, ['dep', 'depth', 'Depth', 'DEP']);
                $accessInfo = $this->extractFirstText($entry, ['Access', 'access', 'access_info', 'AccessInfo', 'access notes']);
                $tagsText = $this->extractFirstText($entry, ['tags', 'Tags', 'tag', 'Tag']);
                $noKnownAccess = $this->hasNoKnownAccess($accessInfo);
                $hasPointGeometry = $this->hasPointGeometry($entry);
                $sourceId = trim((string) ($entry['id'] ?? $entry['ID'] ?? ''));
                $sourceLink = $this->buildSourceLink($sourceId, $entry, $url);
                $detailData = $this->fetchDetailDataByEntry($entry, $sourceId, $sourceLink);
                $length = $this->preferPositiveFloat($length, $detailData['length'] ?? 0.0);
                $depth = $this->preferPositiveFloat($depth, $detailData['depth'] ?? 0.0);
                $accessInfo = $accessInfo ?: (string) ($detailData['access'] ?? '');
                $tagsText = trim($tagsText.($tagsText && !empty($detailData['tags']) ? ', ' : '').($detailData['tags'] ?? ''));
                $descriptionFromDetail = (string) ($detailData['description'] ?? '');
                if (empty($sourceId)) {
                    $sourceId = (string) ($detailData['id'] ?? '');
                }
                if (!empty($detailData['source_link'])) {
                    $sourceLink = (string) $detailData['source_link'];
                }
                $noKnownAccess = $this->hasNoKnownAccess($accessInfo);

                if ($this->isLikelyConnectionRecord($name, $length, $depth, $accessInfo, $hasPointGeometry)) {
                    ++$skippedCount;
                    continue;
                }

                // Apply Filters
                $isWhitelisted = in_array(strtolower($name), array_map('strtolower', $whitelistNames));
                $isBlocklisted = in_array(strtolower($name), array_map('strtolower', $blocklistNames));
                $isLongEnough = $length >= $minLength;

                if ($isBlocklisted) {
                    ++$skippedCount;
                    continue;
                }

                if ($length <= 1 && !$isWhitelisted) {
                    ++$skippedCount;
                    continue;
                }

                if ($this->hasLostTag($tagsText) && !$isWhitelisted) {
                    ++$skippedCount;
                    continue;
                }

                if ($skipUnknownAccess && $noKnownAccess && !$isWhitelisted) {
                    ++$skippedCount;
                    continue;
                }

                if (!$isWhitelisted && !$isLongEnough) {
                    ++$skippedCount;
                    continue;
                }

                $statusLabel = $isWhitelisted ? 'Whitelisted' : 'Length: '.$length.' m';
                $this->line("Processing: {$name} <fg=gray>({$statusLabel})</> <fg=blue>[{$sourceLink}]</>");
                ++$importedCount;

                if ($dryRun) {
                    continue;
                }

                // 1. Cave System
                $systemName = $this->extractFirstText($entry, ['System', 'system', 'SystemName', 'system_name']) ?: $name;
                $systemLength = (int) round($length);
                $systemDepth = (int) round($depth);
                $baseSystemSlug = Str::slug($systemName);
                $system = CaveSystem::where('name', $systemName)->first()
                    ?? CaveSystem::where('slug', $baseSystemSlug)->first();

                if (!$system) {
                    $system = CaveSystem::create([
                        'name' => $systemName,
                        'slug' => $this->uniqueSlug($baseSystemSlug, 'cave_systems'),
                        'length' => $systemLength,
                        'vertical_range' => $systemDepth,
                    ]);
                }

                if ($length > 0 || $depth > 0) {
                    $system->length = max((int) $system->length, $systemLength);
                    $system->vertical_range = max((int) $system->vertical_range, $systemDepth);
                    $system->save();
                }

                $caveSystemId = $system->id;

                // 2. Geolocation
                $lat = 0;
                $lng = 0;
                $alt = round($this->extractFirstFloat($entry, ['alt', 'elevation', 'Altitude', 'altitude']), 1);

                try {
                    $eStr = trim(str_replace(' ', '', (string) ($entry['E'] ?? '')));
                    $nStr = trim(str_replace(' ', '', (string) ($entry['N'] ?? '')));
                    $grStr = trim(str_replace(' ', '', (string) ($entry['GR'] ?? '')));

                    if (!empty($grStr) && !empty($eStr) && !empty($nStr) && strlen($eStr) === strlen($nStr)) {
                        $ref = $grStr.$eStr.$nStr;
                        $gridPoint = \PHPCoord\Point\BritishNationalGridPoint::fromGridReference($ref);
                        $wgs84 = $gridPoint->convert(\PHPCoord\CoordinateReferenceSystem\Geographic2D::fromSRID('urn:ogc:def:crs:EPSG::4326'));
                        $lat = round($wgs84->getLatitude()->asDegrees()->getValue(), 5);
                        $lng = round($wgs84->getLongitude()->asDegrees()->getValue(), 5);
                    } else {
                        throw new \Exception('Invalid or unequal E/N sizes');
                    }
                } catch (\Exception $e) {
                    // Fallback to LL on any parsing error or unequal length
                    $llStr = (string) ($entry['LL'] ?? '');
                    if (!empty($llStr)) {
                        $ll = explode(',', $llStr);
                        // Standard order in this XML file: Longitude, Latitude
                        $lng = round((float) ($ll[0] ?? 0), 5);
                        $lat = round((float) ($ll[1] ?? 0), 5);
                    } else {
                        $lat = $this->extractFirstFloat($entry, ['lat', 'latitude', 'Latitude']);
                        $lng = $this->extractFirstFloat($entry, ['lng', 'lon', 'longitude', 'Longitude']);
                        if ($lat === 0.0 && $lng === 0.0) {
                            [$lat, $lng, $coordAlt] = $this->extractCoordinatesFromEntry($entry);
                            if ($alt === 0.0 && $coordAlt !== 0.0) {
                                $alt = $coordAlt;
                            }
                        }
                    }
                }

                if ($lat === 0.0 && $lng === 0.0) {
                    $detailLat = (float) ($detailData['lat'] ?? 0.0);
                    $detailLng = (float) ($detailData['lng'] ?? 0.0);
                    if ($detailLat !== 0.0 || $detailLng !== 0.0) {
                        $lat = $detailLat;
                        $lng = $detailLng;
                    }
                }

                // 3. Description & References
                $descriptionParts = [];
                $descText = $this->extractFirstText($entry, ['Desc', 'desc', 'Description', 'description']);
                if (empty($descText)) {
                    $descText = $descriptionFromDetail;
                }
                if (!empty($descText)) {
                    $descriptionParts[] = $descText;
                }
                if (empty($accessInfo)) {
                    $accessInfo = $descText;
                    $noKnownAccess = $this->hasNoKnownAccess($accessInfo);
                    if ($skipUnknownAccess && $noKnownAccess && !$isWhitelisted) {
                        ++$skippedCount;
                        continue;
                    }
                }

                $descriptionParts[] = '[MCRA Registry]('.$sourceLink.')';

                $description = implode("\n\n", $descriptionParts);

                {
                    $systemReferences = ['[MCRA Registry]('.$sourceLink.')'];
                    $referenceNodes = [];
                    foreach (['Bibl', 'bibl', 'Reference', 'reference'] as $refNode) {
                        foreach ($entry->{$refNode} ?? [] as $node) {
                            $referenceNodes[] = $node;
                        }
                    }
                    $singleRef = $this->extractFirstText($entry, ['reference', 'Reference', 'Bibliography', 'bibliography']);
                    if (!empty($singleRef)) {
                        $systemReferences[] = $singleRef;
                    }
                    if (!empty($referenceNodes)) {
                        foreach ($referenceNodes as $bibl) {
                            $biblText = $this->xmlInnerText($bibl);
                            if (!empty($biblText)) {
                                $systemReferences[] = $biblText;
                            }
                        }
                    }
                    // Deduplicate entries from source feed
                    $systemReferences = array_values(array_unique($systemReferences));

                    // Format as markdown list items
                    $formattedNewRefs = array_map(fn ($r) => '- '.$r, $systemReferences);

                    if ($system->wasRecentlyCreated) {
                        // New system: set references directly
                        $system->references = implode("\n", $formattedNewRefs);
                        $system->save();
                    } else {
                        // Existing system: check for new references using normalized comparison
                        $existingRefs = !empty($system->references) ? explode("\n", $system->references) : [];
                        $normalizedExisting = array_map(
                            fn ($ref) => strtolower(trim(preg_replace('/^-\s*/', '', $ref))),
                            $existingRefs
                        );
                        $existingRefsLower = strtolower($system->references ?? '');

                        $newRefs = array_filter($systemReferences, function ($ref) use ($normalizedExisting, $existingRefsLower) {
                            $normalizedRef = strtolower(trim($ref));

                            // Exact line match (for properly-formatted refs)
                            // or substring match (for legacy refs stored in concatenated lines)
                            return !in_array($normalizedRef, $normalizedExisting)
                                && !str_contains($existingRefsLower, $normalizedRef);
                        });

                        if (!empty($newRefs)) {
                            // Build suggested value: keep existing entries, append new ones as list items
                            $suggestedRefs = array_merge($existingRefs, array_map(fn ($r) => '- '.$r, $newRefs));
                            $suggestedValue = implode("\n", $suggestedRefs);

                            $existingPendingEdit = \App\Models\SuggestedEdit::where('suggestable_type', CaveSystem::class)
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
                                \App\Models\SuggestedEdit::create([
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
                }

                // 4. Create/Update Cave
                // Note: slug and cave_system_id are excluded from diff checking — they are
                // internal fields that should not appear as suggested edits.

                // Build region-prefixed slug
                $regions = $entry->xpath('ancestor::Region') ?: [];
                $regionName = $regions ? (string) ($regions[0]['name'] ?? '') : '';
                if (empty($regionName)) {
                    $regionName = $this->extractFirstText($entry, ['Region', 'region', 'Area', 'area']);
                }
                $regionNameLower = strtolower($regionName);

                $regionPrefix = '';
                if (strpos($regionNameLower, 'north wales') !== false) {
                    $regionPrefix = 'north_wales_';
                } elseif (strpos($regionNameLower, 'mendip') !== false) {
                    $regionPrefix = 'mendips_';
                } elseif (
                    strpos($regionNameLower, 'south') !== false ||
                    strpos($regionNameLower, 'gower') !== false ||
                    strpos($regionNameLower, 'northern outcrop') !== false
                ) {
                    $regionPrefix = 'south_wales_';
                }

                $baseSlug = $regionPrefix.Str::slug($name);

                $caveData = [
                    'description' => $description,
                    'location_name' => $regionName ?: ($this->extractFirstText($entry, ['location', 'Location']) ?: 'Unknown'),
                    'location_country' => 'United Kingdom',
                    'location_lat' => $lat,
                    'location_lng' => $lng,
                    'location_alt' => $alt,
                    'access_info' => $accessInfo ?: null,
                ];

                $existingCave = Cave::where('name', $name)->first()
                    ?? Cave::where('slug', $baseSlug)->first();

                if ($existingCave) {
                    // Check for differences. Round both sides before comparing floats to avoid
                    // false negatives from legacy high-precision stored values.
                    $coordKeys = ['location_lat', 'location_lng', 'location_alt'];
                    // Large text fields: skip if source text is already contained in Subterra text
                    $textKeys = ['description', 'access_info'];
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
                            // Skip if both empty or if every paragraph of source text is already
                            // contained in Subterra text (accounts for formatting differences
                            // like angle-bracket-wrapped URLs from previous imports).
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
                            $originalData[$key] = in_array($key, ['location_lat', 'location_lng', 'location_alt'])
                                ? round((float) $val, 5)
                                : $val;
                        }

                        $existingPendingEdit = \App\Models\SuggestedEdit::where('suggestable_type', Cave::class)
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
                            \App\Models\SuggestedEdit::create([
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
                    $cave = $existingCave;
                } else {
                    $cave = Cave::create(array_merge([
                        'name' => $name,
                        'slug' => $this->uniqueSlug($baseSlug, 'caves'),
                        'cave_system_id' => $caveSystemId,
                    ], $caveData));
                    $this->line("<fg=green>  ✚ New cave created:</> {$name}");
                    ++$newCaveCount;
                }

                // 5. Sync Tags
                $tagIds = [];

                // General "cave" tag
                $tagCave = \App\Models\Tag::where('tag', 'Cave')->where('category', 'type')->first();
                if ($tagCave) {
                    $tagIds[] = $tagCave->id;
                }

                if (strpos($regionNameLower, 'north wales') !== false) {
                    $tagNorthWales = \App\Models\Tag::where('tag', 'North Wales')->where('category', 'region')->first();
                    if ($tagNorthWales) {
                        $tagIds[] = $tagNorthWales->id;
                    }
                } elseif (strpos($regionNameLower, 'mendip') !== false) {
                    $tagMendip = \App\Models\Tag::where('tag', 'Mendip')->where('category', 'region')->first();
                    if ($tagMendip) {
                        $tagIds[] = $tagMendip->id;
                    }
                } elseif (
                    strpos($regionNameLower, 'south') !== false ||
                    strpos($regionNameLower, 'gower') !== false ||
                    strpos($regionNameLower, 'northern outcrop') !== false
                ) {
                    $tagSouthWales = \App\Models\Tag::where('tag', 'South Wales')->where('category', 'region')->first();
                    if ($tagSouthWales) {
                        $tagIds[] = $tagSouthWales->id;
                    }
                }

                if (!empty($tagIds)) {
                    $cave->tags()->syncWithoutDetaching($tagIds);
                }
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
     * Get list of whitelisted names from option or file.
     *
     * @return array
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
            $fileContent = file_get_contents($filePath);
            $names = array_map('trim', explode("\n", $fileContent));
            // Filter empty lines and comments
            $names = array_filter($names, fn ($name) => !empty($name) && !str_starts_with($name, '#'));
            $whitelist = array_merge($whitelist, array_values($names));
        }

        return $whitelist;
    }

    /**
     * Get list of blocklisted names from option or file.
     *
     * @return array
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
            $fileContent = file_get_contents($filePath);
            $names = array_map('trim', explode("\n", $fileContent));
            // Filter empty lines and comments
            $names = array_filter($names, fn ($name) => !empty($name) && !str_starts_with($name, '#'));
            $blocklist = array_merge($blocklist, array_values($names));
        }

        return $blocklist;
    }

    private function uniqueSlug(string $base, string $table): string
    {
        $slug = $base;
        $count = 2;
        while (\Illuminate\Support\Facades\DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$count++;
        }

        return $slug;
    }

    private function textAlreadyContained(string $newText, string $existingText): bool
    {
        if (empty($existingText)) {
            return false;
        }

        // Check each paragraph/part of the new text individually.
        // This handles formatting differences (e.g. URLs wrapped in <> brackets,
        // trailing <br/> tags) from previous imports or manual edits.
        $parts = preg_split('/\n{2,}/', trim($newText));

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if (!str_contains($existingText, $part)) {
                // For MCRA Registry links, check if the ID is already referenced
                // regardless of link format (markdown, angle-bracket, plain URL)
                if (preg_match('/MCRA Registry/i', $part) && preg_match('/[?&]ID=(\d+)/i', $part, $idMatch)) {
                    if (str_contains($existingText, 'ID='.$idMatch[1])) {
                        continue;
                    }
                }

                return false;
            }
        }

        return true;
    }

    /**
     * Extract inner text from a SimpleXML element, preserving text from child elements.
     *
     * SimpleXML's (string) cast only returns direct text nodes, dropping content
     * inside child elements like <a href="...">link text</a>. This method gets
     * the full inner XML and strips tags to produce complete plain text.
     */
    private function xmlInnerText(\SimpleXMLElement $element): string
    {
        $innerXml = $element->asXML();
        // Remove the outer element tags
        $tagName = $element->getName();
        $innerXml = preg_replace('/^<'.$tagName.'[^>]*>/', '', $innerXml);
        $innerXml = preg_replace('/<\/'.$tagName.'>$/', '', $innerXml);

        return trim(strip_tags($innerXml));
    }

    private function extractFirstText(\SimpleXMLElement $entry, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim($this->extractFieldValue($entry, $key));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function extractFirstFloat(\SimpleXMLElement $entry, array $keys): float
    {
        foreach ($keys as $key) {
            $value = trim($this->extractFieldValue($entry, $key));
            if ($value !== '' && is_numeric($value)) {
                return (float) $value;
            }
        }

        return 0.0;
    }

    private function extractFieldValue(\SimpleXMLElement $entry, string $key): string
    {
        $keyVariants = array_values(array_unique([$key, strtolower($key), strtoupper($key), ucfirst($key)]));

        foreach ($keyVariants as $variant) {
            if (isset($entry[$variant])) {
                return trim((string) $entry[$variant]);
            }

            if (isset($entry->{$variant})) {
                return trim($this->xmlInnerText($entry->{$variant}));
            }
        }

        $lowerKey = strtolower($key);

        $dataMatches = $entry->xpath('.//*[local-name()="Data" and translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="'.$lowerKey.'"]/*[local-name()="value"]') ?: [];
        if (!empty($dataMatches)) {
            return trim((string) $dataMatches[0]);
        }

        $simpleDataMatches = $entry->xpath('.//*[local-name()="SimpleData" and translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="'.$lowerKey.'"]') ?: [];
        if (!empty($simpleDataMatches)) {
            return trim((string) $simpleDataMatches[0]);
        }

        $namedNodeMatches = $entry->xpath('.//*[translate(local-name(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="'.$lowerKey.'"]') ?: [];
        if (!empty($namedNodeMatches)) {
            return trim($this->xmlInnerText($namedNodeMatches[0]));
        }

        return '';
    }

    private function extractCoordinatesFromEntry(\SimpleXMLElement $entry): array
    {
        $coordNodes = $entry->xpath('.//*[local-name()="Point"]/*[local-name()="coordinates"]') ?: [];
        if (empty($coordNodes)) {
            $coordNodes = $entry->xpath('.//*[local-name()="coordinates"]') ?: [];
        }
        if (empty($coordNodes)) {
            return [0.0, 0.0, 0.0];
        }

        $coordText = trim((string) $coordNodes[0]);
        if ($coordText === '') {
            return [0.0, 0.0, 0.0];
        }

        $firstTuple = preg_split('/\s+/', $coordText)[0] ?? '';
        $parts = array_map('trim', explode(',', $firstTuple));
        $lng = is_numeric($parts[0] ?? null) ? (float) $parts[0] : 0.0;
        $lat = is_numeric($parts[1] ?? null) ? (float) $parts[1] : 0.0;
        $alt = is_numeric($parts[2] ?? null) ? (float) $parts[2] : 0.0;

        return [round($lat, 5), round($lng, 5), round($alt, 1)];
    }

    private function hasPointGeometry(\SimpleXMLElement $entry): bool
    {
        $pointNodes = $entry->xpath('.//*[local-name()="Point"]') ?: [];

        return !empty($pointNodes);
    }

    private function isLikelyConnectionRecord(string $name, float $length, float $depth, string $accessInfo, bool $hasPointGeometry): bool
    {
        $looksLikeConnectionName = preg_match('/\s+to\s+/i', trim($name)) === 1;
        $hasNoMetrics = $length <= 0 && $depth <= 0;
        $hasNoAccess = trim($accessInfo) === '';

        if (!$hasPointGeometry && $looksLikeConnectionName) {
            return true;
        }

        if ($looksLikeConnectionName && $hasNoMetrics && $hasNoAccess) {
            return true;
        }

        return false;
    }

    private function hasNoKnownAccess(string $accessInfo): bool
    {
        if (empty($accessInfo)) {
            return false;
        }

        $normalized = strtolower(trim($accessInfo));
        $phrases = [
            'no known access',
            'unknown access',
            'access unknown',
            'no current access',
        ];

        foreach ($phrases as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function hasLostTag(string $tagsText): bool
    {
        if (trim($tagsText) === '') {
            return false;
        }

        return preg_match('/(?:^|[\\s,;|])lost(?:$|[\\s,;|])/i', $tagsText) === 1;
    }

    private function buildSourceLink(string $sourceId, \SimpleXMLElement $entry, string $feedUrl): string
    {
        $referenceUrl = $this->extractFirstText($entry, ['reference', 'Reference', 'detail_url', 'DetailUrl']);
        if (!empty($referenceUrl) && filter_var($referenceUrl, FILTER_VALIDATE_URL)) {
            return $referenceUrl;
        }

        $entryUrl = $this->extractFirstText($entry, ['URL', 'Url', 'Link', 'link']);
        if (!empty($entryUrl) && filter_var($entryUrl, FILTER_VALIDATE_URL)) {
            return $entryUrl;
        }

        $idFromEntry = $sourceId;
        if ($idFromEntry === '') {
            $idFromEntry = $this->extractFirstText($entry, ['id', 'ID', 'siteid', 'SiteID']);
        }
        if ($idFromEntry === '') {
            $idFromEntry = $this->extractIdFromUrl($referenceUrl ?: $entryUrl);
        }

        if ($idFromEntry !== '') {
            return 'https://www.mcra.org.uk/registry/sitedetails.php?id='.rawurlencode($idFromEntry);
        }

        return $feedUrl;
    }

    private function fetchDetailDataByEntry(\SimpleXMLElement $entry, string $sourceId, string $sourceLink): array
    {
        $detailUrl = '';

        if ($sourceLink !== '' && str_contains(strtolower($sourceLink), 'sitedetails.php')) {
            $detailUrl = $sourceLink;
        }

        if ($detailUrl === '') {
            $referenceUrl = $this->extractFirstText($entry, ['reference', 'Reference', 'detail_url', 'DetailUrl']);
            if (filter_var($referenceUrl, FILTER_VALIDATE_URL) && str_contains(strtolower($referenceUrl), 'sitedetails.php')) {
                $detailUrl = $referenceUrl;
            }
        }

        if ($detailUrl === '') {
            $entryUrl = $this->extractFirstText($entry, ['URL', 'Url', 'Link', 'link', 'href', 'Href']);
            if (filter_var($entryUrl, FILTER_VALIDATE_URL) && str_contains(strtolower($entryUrl), 'sitedetails.php')) {
                $detailUrl = $entryUrl;
            }
        }

        $id = trim($sourceId);
        if ($id === '') {
            $id = $this->extractFirstText($entry, ['id', 'ID', 'siteid', 'SiteID']);
        }
        if ($id === '') {
            $id = $this->extractIdFromUrl($sourceLink);
        }
        if ($id === '' && $detailUrl !== '') {
            $id = $this->extractIdFromUrl($detailUrl);
        }

        if ($detailUrl === '' && $id !== '') {
            $detailUrl = 'https://www.mcra.org.uk/registry/sitedetails.php?id='.rawurlencode($id);
        }

        if ($detailUrl === '') {
            return ['id' => $id];
        }

        $detail = $this->fetchBrowseDetailData($detailUrl);
        $detail['id'] = $detail['id'] ?? $id;
        $detail['source_link'] = $detailUrl;

        return $detail;
    }

    private function preferPositiveFloat(float $primary, float $fallback): float
    {
        if ($primary > 0) {
            return $primary;
        }

        if ($fallback > 0) {
            return $fallback;
        }

        return $primary;
    }

    private function resolveRelativeUrl(string $baseUrl, string $href): string
    {
        if (preg_match('/^https?:\/\//i', $href)) {
            return $href;
        }

        $base = parse_url($baseUrl);
        if (!$base || empty($base['scheme']) || empty($base['host'])) {
            return $href;
        }

        $scheme = $base['scheme'];
        $host = $base['host'];
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        if (str_starts_with($href, '/')) {
            return "{$scheme}://{$host}{$port}{$href}";
        }

        $path = $base['path'] ?? '/';
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        $dir = $dir === '' ? '' : $dir;

        return "{$scheme}://{$host}{$port}{$dir}/{$href}";
    }

    private function parseBrowseHtmlEntries(string $initialHtml, string $feedUrl, bool $showLiveCount = false): array
    {
        $entries = [];
        $totalPages = $this->extractTotalPagesFromBrowseHtml($initialHtml);
        $tmp = [];
        parse_str(parse_url($feedUrl, PHP_URL_QUERY) ?? '', $tmp);
        $basePage = (int) ($tmp['page'] ?? 0);
        $maxPages = min(max($totalPages, 1), 250);
        $readCount = 0;

        for ($pageOffset = 0; $pageOffset < $maxPages; $pageOffset++) {
            $pageIndex = $basePage + $pageOffset;
            $html = $pageOffset === 0 ? $initialHtml : $this->downloadBrowsePage($feedUrl, $pageIndex);
            if (empty($html)) {
                continue;
            }

            $rows = $this->extractBrowseRows($html, $feedUrl);
            foreach ($rows as $row) {
                $entryXml = $this->buildXmlEntryFromBrowseRow($row);
                $entry = @simplexml_load_string($entryXml);
                if ($entry !== false) {
                    $entries[] = $entry;
                    ++$readCount;
                }
            }

            if ($showLiveCount) {
                $pageLabel = $pageOffset + 1;
                $this->output->write("\rRead caves: {$readCount} (page {$pageLabel}/{$maxPages})");
            }
        }

        if ($showLiveCount) {
            $this->newLine();
        }

        return $entries;
    }

    private function extractTotalPagesFromBrowseHtml(string $html): int
    {
        if (preg_match('/Page\\s+\\d+\\s+of\\s+(\\d+)/i', $html, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }

    private function downloadBrowsePage(string $feedUrl, int $page): string
    {
        $parts = parse_url($feedUrl);
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['page'] = $page;
        $queryString = http_build_query($query);

        $path = ($parts['path'] ?? '').($queryString ? '?'.$queryString : '');
        $target = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').$path;

        try {
            $response = Http::get($target);
            if (!$response->successful()) {
                return '';
            }
        } catch (\Throwable $e) {
            return '';
        }

        return $response->body();
    }

    private function extractBrowseRows(string $html, string $feedUrl): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $rows = $xpath->query('//tr[td]');
        if (!$rows) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($row->childNodes as $child) {
                if ($child instanceof \DOMElement && strtolower($child->tagName) === 'td') {
                    $cells[] = $child;
                }
            }

            if (count($cells) < 6) {
                continue;
            }

            $name = trim($cells[0]->textContent ?? '');
            if ($name === '' || stripos($name, 'name') === 0) {
                continue;
            }

            $tagsText = trim($cells[2]->textContent ?? '');

            $detailUrl = '';
            $link = (new \DOMXPath($dom))->query('.//a[@href]', $cells[0])->item(0);
            if ($link instanceof \DOMElement) {
                $detailUrl = $this->resolveRelativeUrl($feedUrl, $link->getAttribute('href'));
            }

            $detail = $this->fetchBrowseDetailData($detailUrl);

            $out[] = [
                'id' => $this->extractIdFromUrl($detailUrl),
                'name' => $name,
                'system' => $name,
                'length' => $this->extractFirstNumber($cells[3]->textContent ?? ''),
                'depth' => $this->extractFirstNumber($cells[4]->textContent ?? ''),
                'altitude' => $this->extractFirstNumber($cells[5]->textContent ?? ''),
                'region' => $detail['region'] ?? 'Mendip',
                'location' => trim($cells[1]->textContent ?? ''),
                'description' => $detail['description'] ?? '',
                'access' => $detail['access'] ?? '',
                'tags' => $tagsText,
                'reference' => $detailUrl,
                'detail_url' => $detailUrl,
                'lat' => $detail['lat'] ?? 0.0,
                'lng' => $detail['lng'] ?? 0.0,
            ];
        }

        return $out;
    }

    private function extractFirstNumber(string $text): float
    {
        if (preg_match('/-?\\d+(?:\\.\\d+)?/', $text, $m)) {
            return (float) $m[0];
        }

        return 0.0;
    }

    private function extractIdFromUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }

        $query = [];
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

        return isset($query['id']) ? (string) $query['id'] : '';
    }

    private function buildXmlEntryFromBrowseRow(array $row): string
    {
        $name = htmlspecialchars((string) ($row['name'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $system = htmlspecialchars((string) ($row['system'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $desc = htmlspecialchars((string) ($row['description'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $access = htmlspecialchars((string) ($row['access'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $tags = htmlspecialchars((string) ($row['tags'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $region = htmlspecialchars((string) ($row['region'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $reference = htmlspecialchars((string) ($row['reference'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $id = (string) ($row['id'] ?? '');
        $length = (string) ((float) ($row['length'] ?? 0));
        $depth = (string) ((float) ($row['depth'] ?? 0));
        $alt = (string) ((float) ($row['altitude'] ?? 0));
        $lat = (string) ((float) ($row['lat'] ?? 0));
        $lng = (string) ((float) ($row['lng'] ?? 0));

        return <<<XML
<Entry id="{$id}" length="{$length}" dep="{$depth}" alt="{$alt}" lat="{$lat}" lng="{$lng}">
  <Name>{$name}</Name>
  <System>{$system}</System>
  <Region>{$region}</Region>
  <Desc>{$desc}</Desc>
  <Access>{$access}</Access>
  <Tags>{$tags}</Tags>
  <Reference>{$reference}</Reference>
</Entry>
XML;
    }

    private function fetchBrowseDetailData(string $detailUrl): array
    {
        if (empty($detailUrl)) {
            return [];
        }

        if (isset($this->detailPageCache[$detailUrl])) {
            return $this->detailPageCache[$detailUrl];
        }

        try {
            $response = Http::get($detailUrl);
            if (!$response->successful()) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        $html = $response->body();
        $accessText = '';
        $accessLink = '';
        $plainText = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $detailId = $this->extractIdFromUrl($detailUrl);

        if (preg_match('/\\b(access|permit|gated|locked|no access|access information)\\b.{0,300}/is', $html, $m)) {
            $accessText = trim(strip_tags($m[0]));
        }

        if (preg_match('/<a[^>]+href=["\\\']([^"\\\']+)["\\\'][^>]*>[^<]*(access|permit)[^<]*<\\/a>/i', $html, $m)) {
            $accessLink = $this->resolveRelativeUrl($detailUrl, html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $lat = 0.0;
        $lng = 0.0;
        if (preg_match('/WGS84:\\s*<\\/?[^>]*>?.{0,40}?(-?\\d+\\.\\d+)\\s*,\\s*(-?\\d+\\.\\d+)/is', $html, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
        } elseif (preg_match('/(-?\\d+\\.\\d+)\\s*,\\s*(-?\\d+\\.\\d+)/', strip_tags($html), $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
        }

        $length = $this->extractLabeledNumber($plainText, ['length', 'surveyed length', 'total length']);
        $depth = $this->extractLabeledNumber($plainText, ['depth', 'vertical range']);

        $region = '';
        if (preg_match('/\\bregion\\b\\s*[:|]\\s*([^\\n\\r|]{2,120})/i', $plainText, $m)) {
            $region = trim($m[1]);
        }

        $tags = '';
        if (preg_match('/\\btags?\\b\\s*[:|]\\s*([^\\n\\r]{1,200})/i', $plainText, $m)) {
            $tags = trim($m[1]);
        }

        $description = '';
        if (preg_match('/Registry:\\s*\\|[^\\n]*\\n\\s*([^\\n<]{20,400})/i', strip_tags($html), $m)) {
            $description = trim($m[1]);
        }

        $access = trim($accessText);
        if (!empty($accessLink)) {
            $access = trim($access.($access ? "\n\n" : '').'Access information: '.$accessLink);
        }

        $data = [
            'id' => $detailId,
            'region' => $region ?: 'Mendip',
            'description' => $description,
            'access' => $access,
            'tags' => $tags,
            'length' => $length,
            'depth' => $depth,
            'lat' => round($lat, 5),
            'lng' => round($lng, 5),
        ];

        $this->detailPageCache[$detailUrl] = $data;

        return $data;
    }

    private function extractLabeledNumber(string $text, array $labels): float
    {
        foreach ($labels as $label) {
            $quoted = preg_quote($label, '/');
            if (preg_match('/\b'.$quoted.'\b\s*[:|]?\s*(-?\d+(?:\.\d+)?)/i', $text, $m)) {
                return (float) $m[1];
            }
        }

        return 0.0;
    }
}
