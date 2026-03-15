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
                $isLongEnough = $length >= $minLength;

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
                        $lat = $wgs84->getLatitude()->asDegrees()->getValue();
                        $lng = $wgs84->getLongitude()->asDegrees()->getValue();
                    } else {
                        throw new \Exception('Invalid or unequal E/N sizes');
                    }
                } catch (\Exception $e) {
                    // Fallback to LL on any parsing error or unequal length
                    $llStr = (string) ($entry['LL'] ?? '');
                    if (!empty($llStr)) {
                        $ll = explode(',', $llStr);
                        // Standard order in this XML file: Longitude, Latitude
                        $lng = (float) ($ll[0] ?? 0);
                        $lat = (float) ($ll[1] ?? 0);
                    }
                }

                $alt = (float) ($entry['alt'] ?? 0);

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
                $cave = Cave::updateOrCreate(
                    ['name' => $name],
                    [
                        'slug' => Str::slug($name),
                        'description' => $description,
                        'cave_system_id' => $caveSystemId,
                        // Values similar to csv importer defaults
                        // Update location_name from ancestor Region
                        'location_name' => ($entry->xpath('ancestor::Region') ? (string) $entry->xpath('ancestor::Region')[0]['name'] : null),
                        'location_country' => 'United Kingdom',
                        'location_lat' => $lat,
                        'location_lng' => $lng,
                        'location_alt' => $alt,
                        'access_info' => $accessInfo ?: null,
                    ]
                );

                // 6. Sync Tags
                $tagIds = [];

                // General "cave" tag
                $tagCave = \App\Models\Tag::firstOrCreate(
                    ['tag' => 'cave'],
                    ['type' => 'cave', 'category' => 'general']
                );
                $tagIds[] = $tagCave->id;

                // Region tags from ancestor Region in the XML
                $regions = $entry->xpath('ancestor::Region');
                $regionName = $regions ? (string) $regions[0]['name'] : '';
                $regionNameLower = strtolower($regionName);

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
}
