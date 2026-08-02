<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use App\Support\CaveName;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportCaves extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:caves {file : The path to the CSV/TSV file} {--dry-run : parse the file without inserting data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import caves from a CSV or TSV file';

    /**
     * Caves further apart than this (km) are treated as different physical
     * places despite sharing a name. Mirrors CaveName::SAME_PLACE_KM.
     */
    private const SAME_PLACE_KM = 10.0;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $dryRun = $this->option('dry-run');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return 1;
        }

        $this->info("Importing caves from {$filePath}...".($dryRun ? ' [DRY RUN]' : ''));

        // Detect delimiter based on extension or content
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $delimiter = $extension === 'tsv' ? "\t" : ',';

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->error("Unable to open file: {$filePath}");

            return 1;
        }

        // Get headers
        $headers = fgetcsv($handle, 0, $delimiter);

        // Normalize headers: lowercase and trim
        $headers = array_map(function ($h) {
            return trim(strtolower($h));
        }, $headers);

        DB::beginTransaction();

        try {
            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (count($headers) !== count($data)) {
                    $this->warn('Skipping row: column count mismatch.');
                    continue;
                }

                $row = array_combine($headers, $data);
                $name = trim($row['name'] ?? '');

                if (empty($name)) {
                    continue;
                }

                $this->info("Processing: {$name}");

                if ($dryRun) {
                    continue;
                }

                $rowLat = (float) ($row['latitude'] ?? 0);
                $rowLng = (float) ($row['longitude'] ?? 0);

                // Match case-insensitively so a differently-cased name updates
                // the existing cave instead of duplicating it.
                $cave = CaveName::findCave($name);

                // The name match is region-blind: the same name can refer to
                // two different real caves (e.g. Giant's Cave in Mendip and the
                // Peak District). When both sides have coordinates and they are
                // far apart, don't overwrite the existing record — report the
                // row for manual review instead.
                if ($cave && $this->locationConflicts($cave, $rowLat, $rowLng)) {
                    $this->warn(sprintf(
                        "Skipping '%s': existing cave #%d is more than %skm from the row's coordinates — likely a different cave with the same name.",
                        $name,
                        $cave->id,
                        self::SAME_PLACE_KM
                    ));
                    continue;
                }

                // 1. Handle Cave System
                $systemName = trim($row['system'] ?? '');
                if (empty($systemName)) {
                    $systemName = $name;
                }

                // Clean numbers
                $length = (float) str_replace(',', '', $row['length'] ?? '0');
                $depth = (float) str_replace(',', '', $row['depth'] ?? '0');

                // Match case-insensitively so a differently-cased name reuses the
                // existing system rather than creating a duplicate.
                $system = CaveName::findSystem($systemName);
                if (!$system) {
                    $system = CaveSystem::create([
                        'name' => $systemName,
                        'slug' => Str::slug($systemName),
                        'length' => $length, // Assuming singular or system length provided
                        'vertical_range' => $depth,
                    ]);
                }

                // Update system stats if provided and non-zero
                if ($length > 0 || $depth > 0) {
                    $system->length = $length > 0 ? (int) $length : $system->length;
                    $system->vertical_range = $depth > 0 ? (int) $depth : $system->vertical_range;
                    $system->save();
                }

                $caveSystemId = $system->id;

                // 2. Map Location
                $locationName = trim($row['location_name'] ?? 'Unknown');
                if (empty($locationName)) {
                    $locationName = 'Unknown';
                }

                // 3. Prepare Description
                $descriptionParts = [];
                if (!empty($row['description'])) {
                    $descriptionParts[] = $row['description'];
                }
                if (!empty($row['notes'])) {
                    $descriptionParts[] = 'Notes: '.$row['notes'];
                }
                if (!empty($row['references'])) {
                    $descriptionParts[] = 'References: '.$row['references'];
                }
                $description = implode("\n\n", $descriptionParts);

                // 4. Create/Update Cave
                $caveAttributes = [
                    'slug' => Str::slug($name),
                    'description' => $description,
                    'cave_system_id' => $caveSystemId,
                    'location_name' => $locationName,
                    'location_country' => $row['location_country'] ?? 'United Kingdom',
                    'location_lat' => $rowLat,
                    'location_lng' => $rowLng,
                    'location_alt' => (float) ($row['altitude'] ?? 0),
                    'access_info' => $row['access_info'] ?? null,
                ];

                if ($cave) {
                    // Never rename an existing cave's slug (URLs would break,
                    // and a unique-index collision would abort the whole
                    // import) or reassign it to a different system on re-import.
                    $cave->update(Arr::except($caveAttributes, ['slug', 'cave_system_id']));
                } else {
                    $cave = Cave::create(array_merge(['name' => $name], $caveAttributes));
                }

                // 5. Sync Tags
                if (!empty($row['tags'])) {
                    $tags = array_map('trim', explode(',', $row['tags']));
                    $tagIds = [];
                    foreach ($tags as $tagName) {
                        if (empty($tagName)) {
                            continue;
                        }
                        $tag = Tag::firstOrCreate(
                            ['tag' => $tagName],
                            ['type' => 'cave', 'category' => 'general']
                        );
                        $tagIds[] = $tag->id;
                    }
                    $cave->tags()->syncWithoutDetaching($tagIds);
                }
            }

            DB::commit();
            $this->info('Import completed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error during import: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return 1;
        }

        return 0;
    }

    /**
     * Whether an incoming row's coordinates place it substantially away from
     * an existing same-named cave. Rows or caves without usable coordinates
     * never conflict (there is nothing to compare).
     */
    private function locationConflicts(Cave $cave, float $lat, float $lng): bool
    {
        if (!$this->hasCoords($lat, $lng) || !$this->hasCoords((float) $cave->location_lat, (float) $cave->location_lng)) {
            return false;
        }

        return $this->haversineKm((float) $cave->location_lat, (float) $cave->location_lng, $lat, $lng) > self::SAME_PLACE_KM;
    }

    /** Coordinates are usable when not the null-island (0,0) placeholder. */
    private function hasCoords(float $lat, float $lng): bool
    {
        return abs($lat) > 0.0001 || abs($lng) > 0.0001;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthKm * 2 * asin(min(1.0, sqrt($a)));
    }
}
