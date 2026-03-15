<?php

namespace App\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncCccCaves extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:ccc-caves 
                            {--dry-run : Parse the file without inserting data} 
                            {--whitelist= : Comma-separated list of names to always import} 
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
        $whitelistNames = $this->getWhitelist();
        $blocklistNames = $this->getBlocklist();

        $this->info('Fetching CCC data...');
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

        try {
            foreach ($entries as $entry) {
                $name = trim((string) $entry->Name);
                if (empty($name)) {
                    continue;
                }

                $length = (float) ($entry['len'] ?? 0);
                $depth = (float) ($entry['dep'] ?? 0);

                // Apply Filters
                $isWhitelisted = in_array(strtolower($name), array_map('strtolower', $whitelistNames));
                $isBlocklisted = in_array(strtolower($name), array_map('strtolower', $blocklistNames));
                $isLongEnough = $length >= $minLength;

                if ($isBlocklisted) {
                    ++$skippedCount;
                    continue;
                }

                if (!$isWhitelisted && !$isLongEnough) {
                    ++$skippedCount;
                    continue;
                }

                $this->info("Processing: {$name} (".($isWhitelisted ? 'Whitelisted' : 'Length: '.$length.' m').')');
                ++$importedCount;

                if ($dryRun) {
                    continue;
                }

                // 1. Cave System
                // Defaulting System to the Cave Name (same as csv importer).
                $systemName = $name;

                $system = CaveSystem::firstOrCreate(
                    ['name' => $systemName],
                    [
                        'slug' => Str::slug($systemName),
                        'length' => $length,
                        'vertical_range' => $depth,
                    ]
                );

                if ($length > 0 || $depth > 0) {
                    $system->length = max($system->length, (int) $length);
                    $system->vertical_range = max($system->vertical_range, (int) $depth);
                    $system->save();
                }

                $caveSystemId = $system->id;

                // 2. Geolocation (Calculate from GR/E/N using PHPCoord)
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
                        $lat = round($wgs84->getLatitude()->asDegrees()->getValue(), 6);
                        $lng = round($wgs84->getLongitude()->asDegrees()->getValue(), 6);
                    } else {
                        throw new \Exception('Invalid or unequal E/N sizes');
                    }
                } catch (\Exception $e) {
                    // Fallback to LL on any parsing error or unequal length
                    $llStr = (string) ($entry['LL'] ?? '');
                    if (!empty($llStr)) {
                        $ll = explode(',', $llStr);
                        // Standard order in this XML file: Longitude, Latitude
                        $lng = round((float) ($ll[0] ?? 0), 6);
                        $lat = round((float) ($ll[1] ?? 0), 6);
                    }
                }

                $alt = round((float) ($entry['alt'] ?? 0), 1);

                // 3. Description & References
                $descriptionParts = [];
                if (!empty($entry->Desc)) {
                    $descriptionParts[] = (string) $entry->Desc;
                }

                $cccLink = 'https://www.cambriancavingcouncil.org.uk/registry/ccr_registry_view.php?ID='.(string) $entry['id'];
                $descriptionParts[] = 'CCC Registry: '.$cccLink;

                $description = implode("\n\n", $descriptionParts);

                if (!empty($entry->Bibl)) {
                    $systemReferences = [];
                    foreach ($entry->Bibl as $bibl) {
                        $biblText = trim((string) $bibl);
                        if (!empty($biblText)) {
                            $systemReferences[] = $biblText;
                        }
                    }
                    if (!empty($systemReferences)) {
                        $existingRefs = $system->references ? explode("\n", $system->references) : [];
                        $existingRefs = array_merge($existingRefs, $systemReferences);
                        $system->references = implode("\n", array_unique($existingRefs));
                        $system->save();
                    }
                }

                // 4. Access
                $accessInfo = (string) ($entry->Access['con'] ?? '');

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

                $baseSlug = $regionPrefix . Str::slug($name);

                $caveData = [
                    'description' => $description,
                    'location_name' => $regionName ?: null,
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
                    $differences = [];
                    foreach ($caveData as $key => $value) {
                        if (in_array($key, $coordKeys)) {
                            $existingRounded = round((float) $existingCave->$key, 6);
                            $newRounded = round((float) $value, 6);
                            if ($existingRounded !== $newRounded) {
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
                                ? round((float) $val, 6)
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
                            $this->info("Updated suggested edit for existing cave: {$name}");
                        } else {
                            \App\Models\SuggestedEdit::create([
                                'user_id' => null,
                                'suggestable_type' => Cave::class,
                                'suggestable_id' => $existingCave->id,
                                'original_data' => $originalData,
                                'suggested_data' => $differences,
                                'status' => 'pending',
                            ]);
                            $this->info("Created suggested edit for existing cave: {$name}");
                        }
                    }
                    $cave = $existingCave;
                } else {
                    $cave = Cave::create(array_merge([
                        'name' => $name,
                        'slug' => $this->uniqueSlug($baseSlug, 'caves'),
                        'cave_system_id' => $caveSystemId,
                    ], $caveData));
                }

                // 6. Sync Tags
                $tagIds = [];

                // General "cave" tag
                $tagCave = \App\Models\Tag::firstOrCreate(
                    ['tag' => 'cave'],
                    ['type' => 'cave', 'category' => 'general']
                );
                $tagIds[] = $tagCave->id;

                // Region tags from ancestor Region in the XML
                // ($regionName and $regionNameLower already computed above)
                if (strpos($regionNameLower, 'north wales') !== false) {
                    $tagNorthWales = \App\Models\Tag::firstOrCreate(
                        ['tag' => 'North Wales'],
                        ['type' => 'cave', 'category' => 'region']
                    );
                    $tagIds[] = $tagNorthWales->id;
                } elseif (
                    strpos($regionNameLower, 'south') !== false ||
                    strpos($regionNameLower, 'gower') !== false ||
                    strpos($regionNameLower, 'northern outcrop') !== false
                ) {
                    $tagSouthWales = \App\Models\Tag::firstOrCreate(
                        ['tag' => 'South Wales'],
                        ['type' => 'cave', 'category' => 'region']
                    );
                    $tagIds[] = $tagSouthWales->id;
                }

                if (!empty($tagIds)) {
                    $cave->tags()->syncWithoutDetaching($tagIds);
                }
            }

            if (!$dryRun) {
                DB::commit();
                $this->info("Import completed: {$importedCount} imported/updated, {$skippedCount} skipped.");
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

        $filePath = storage_path('app/ccc_whitelist.txt');
        if (file_exists($filePath)) {
            $fileContent = file_get_contents($filePath);
            $names = array_map('trim', explode("\n", $fileContent));
            // Filter empty lines
            $names = array_filter($names, fn ($name) => !empty($name));
            $whitelist = array_merge($whitelist, array_values($names));
        }

        return $whitelist;
    }
    /**
     * Get list of backlisted names from option or file.
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

        $filePath = storage_path('app/ccc_blocklist.txt');
        if (file_exists($filePath)) {
            $fileContent = file_get_contents($filePath);
            $names = array_map('trim', explode("\n", $fileContent));
            $names = array_filter($names, fn ($name) => !empty($name));
            $blocklist = array_merge($blocklist, array_values($names));
        }

        return $blocklist;
    }

    private function uniqueSlug(string $base, string $table): string
    {
        $slug = $base;
        $count = 2;
        while (\Illuminate\Support\Facades\DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $count++;
        }

        return $slug;
    }
}
