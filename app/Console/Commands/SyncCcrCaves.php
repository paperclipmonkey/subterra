<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use App\Support\CaveName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncCcrCaves extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:ccr-caves 
                            {--dry-run : Parse the file without inserting data} 
                            {--blocklist= : Comma-separated list of names to always skip}
                            {--min-length=250 : Minimum length in meters to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync caves from Cambrian Caving Council XML registry';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $minLength = (float) $this->option('min-length');
        $blocklistNames = $this->getBlocklist();

        $this->info('Fetching CCR data...');
        $url = 'https://cambriancavingcouncil.org.uk/registry/CCR_data2.xml';

        try {
            $response = Http::get($url);
            if (!$response->successful()) {
                $this->error("Failed to download XML from: {$url}");

                return 1;
            }
        } catch (\Exception $e) {
            $this->error('HTTP Request failed: '.$e->getMessage());

            return 1;
        }

        $xmlStr = $response->body();
        $xml = @simplexml_load_string($xmlStr);

        if ($xml === false) {
            $this->error('Failed to parse XML.');

            return 1;
        }

        $entries = $xml->xpath('//Entry');
        if (empty($entries)) {
            $this->warn('No <Entry> nodes found in XML.');

            return 0;
        }

        $this->info('Found '.count($entries).' entries in XML.');

        DB::beginTransaction();

        $importedCount = 0;
        $skippedCount = 0;
        $newCaveCount = 0;
        $suggestedEditCount = 0;
        $noOpCount = 0;

        try {
            foreach ($entries as $entry) {
                $name = trim((string) $entry->Name);
                if (empty($name)) {
                    continue;
                }

                // CCR wraps a figure in square brackets when it refers to the whole
                // system rather than this individual entrance (e.g. len="[70000]" on a
                // second entrance to Ogof Draenen). Strip non-numeric chars so these
                // parse instead of casting to 0 and getting dropped by the length filter.
                $length = (float) preg_replace('/[^0-9.\-]/', '', (string) ($entry['len'] ?? ''));
                $depth = (float) preg_replace('/[^0-9.\-]/', '', (string) ($entry['dep'] ?? ''));

                // Apply Filters
                $isBlocklisted = in_array(strtolower($name), array_map('strtolower', $blocklistNames));
                $isLongEnough = $length >= $minLength;

                if ($isBlocklisted) {
                    ++$skippedCount;
                    continue;
                }

                if (!$isLongEnough) {
                    ++$skippedCount;
                    continue;
                }

                $this->line("Processing: {$name} <fg=gray>(Length: {$length} m)</>");
                ++$importedCount;

                if ($dryRun) {
                    continue;
                }

                // 1. Geolocation (Calculate from GR/E/N using PHPCoord)
                // Resolved before the system/cave lookup so it can disambiguate
                // same-named places in different regions by proximity.
                $lat = 0;
                $lng = 0;

                try {
                    $eStr = trim(str_replace(' ', '', (string) $entry['E']));
                    $nStr = trim(str_replace(' ', '', (string) $entry['N']));
                    $grStr = trim(str_replace(' ', '', (string) $entry['GR']));

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
                    }
                }

                $alt = round((float) ($entry['alt'] ?? 0), 1);

                // 2. Cave System
                // Defaulting System to the Cave Name (same as csv importer).
                $systemName = $name;
                $systemSlug = Str::slug($systemName);

                $system = CaveName::findSystemForRegistry($systemName, $systemSlug, 'ccr', $lat, $lng);

                if (!$system) {
                    $system = CaveSystem::create([
                        'name' => $systemName,
                        'slug' => $this->uniqueSlug($systemSlug, 'cave_systems'),
                        'length' => $length,
                        'vertical_range' => $depth,
                    ]);
                }

                if ($length > 0 || $depth > 0) {
                    $system->length = max($system->length, (int) $length);
                    $system->vertical_range = max($system->vertical_range, (int) $depth);
                    $system->save();
                }

                $caveSystemId = $system->id;

                // 3. Description & References
                $descriptionParts = [];
                if (!empty($entry->Desc)) {
                    $descriptionParts[] = $this->xmlInnerText($entry->Desc);
                }

                $ccrLink = 'https://www.cambriancavingcouncil.org.uk/registry/ccr_registry_view.php?ID='.(string) $entry['id'];
                $descriptionParts[] = '[CC Registry]('.$ccrLink.')';

                $description = implode("\n\n", $descriptionParts);

                {
                    $systemReferences = ['[CC Registry]('.$ccrLink.')'];
                    if (!empty($entry->Bibl)) {
                        foreach ($entry->Bibl as $bibl) {
                            $biblText = $this->xmlInnerText($bibl);
                            if (!empty($biblText)) {
                                $systemReferences[] = $biblText;
                            }
                        }
                    }
                    // Deduplicate XML entries (CCR data can have duplicate Bibl elements)
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

                // 4. Access (text content only — the 'con' attribute is not useful)
                $accessInfo = !empty($entry->Access) ? $this->xmlInnerText($entry->Access) : '';

                // 5. Create/Update Cave
                // Note: slug and cave_system_id are excluded from diff checking — they are
                // internal fields that should not appear as suggested edits.

                // Build region-prefixed slug (e.g. north_wales_cave_name)
                $regions = $entry->xpath('ancestor::Region');
                $regionName = $regions ? (string) $regions[0]['name'] : '';
                $regionNameLower = strtolower($regionName);

                $regionPrefix = '';
                if (strpos($regionNameLower, 'north wales') !== false) {
                    $regionPrefix = 'north_wales_';
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
                    'location_name' => $regionName ?: null,
                    'location_country' => 'United Kingdom',
                    'location_lat' => $lat,
                    'location_lng' => $lng,
                    'location_alt' => $alt,
                    'access_info' => $accessInfo ?: null,
                ];

                $ccrId = (string) $entry['id'];
                $existingCave = Cave::where('registry', 'ccr')->where('registry_id', $ccrId)->first()
                    ?? CaveName::findCaveForRegistry($name, $baseSlug, 'ccr', $lat, $lng);

                if ($existingCave) {
                    // Check for differences. Round both sides before comparing floats to avoid
                    // false negatives from legacy high-precision stored values.
                    $coordKeys = ['location_lat', 'location_lng', 'location_alt'];
                    // Large text fields: skip if CCR text is already contained in Subterra text
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
                            // Skip if both empty or if every paragraph of CCR text is already
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
                    // Persist registry tracking so future syncs can match by ID
                    if (empty($existingCave->registry) || empty($existingCave->registry_id)) {
                        $existingCave->registry = 'ccr';
                        $existingCave->registry_id = $ccrId;
                        $existingCave->save();
                    }
                    $cave = $existingCave;
                } else {
                    $cave = Cave::create(array_merge([
                        'name' => $name,
                        'slug' => $this->uniqueSlug($baseSlug, 'caves'),
                        'cave_system_id' => $caveSystemId,
                        'registry' => 'ccr',
                        'registry_id' => $ccrId,
                    ], $caveData));
                    $this->line("<fg=green>  ✚ New cave created:</> {$name}");
                    ++$newCaveCount;
                }

                // 6. Sync Tags
                $tagIds = [];

                // General "cave" tag
                $tagCave = \App\Models\Tag::where('tag', 'Cave')->where('category', 'type')->firstOrFail();
                $tagIds[] = $tagCave->id;

                // Region tags from ancestor Region in the XML
                // ($regionName and $regionNameLower already computed above)
                if (strpos($regionNameLower, 'north wales') !== false) {
                    $tagNorthWales = \App\Models\Tag::where('tag', 'North Wales')->where('category', 'region')->firstOrFail();
                    $tagIds[] = $tagNorthWales->id;
                } elseif (
                    strpos($regionNameLower, 'south') !== false ||
                    strpos($regionNameLower, 'gower') !== false ||
                    strpos($regionNameLower, 'northern outcrop') !== false
                ) {
                    $tagSouthWales = \App\Models\Tag::where('tag', 'South Wales')->where('category', 'region')->firstOrFail();
                    $tagIds[] = $tagSouthWales->id;
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

        $filePath = storage_path('app/ccr_blocklist.txt');
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

        // Normalize line endings before comparing. Legacy stored values use CRLF
        // (\r\n) while text parsed from the CCR XML uses LF (\n); without this a
        // field that differs only in line endings would register as a change and
        // produce a no-op suggested edit.
        $newText = str_replace("\r\n", "\n", $newText);
        $existingText = str_replace("\r\n", "\n", $existingText);

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
                // For CC Registry links, check if the ID is already referenced
                // regardless of link format (markdown, angle-bracket, plain URL)
                if (preg_match('/CC Registry/i', $part) && preg_match('/[?&]ID=(\d+)/', $part, $idMatch)) {
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

        // strip_tags removes the real markup but leaves entities encoded, and
        // asXML() re-encodes literal characters (& -> &amp;, < -> &lt;). Decode
        // after stripping so the plain text matches what is stored in Subterra
        // (otherwise "capped & gated" parses as "capped &amp; gated" and a no-op
        // suggested edit is created). Strip first, then decode, so encoded angle
        // brackets in the text aren't mistaken for tags.
        return trim(html_entity_decode(strip_tags($innerXml), ENT_QUOTES | ENT_HTML5));
    }
}
