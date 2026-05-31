<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use Illuminate\Console\Command;
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

                // 1. Handle Cave System
                $systemName = trim($row['system'] ?? '');
                if (empty($systemName)) {
                    $systemName = $name;
                }

                // Clean numbers
                $length = (float) str_replace(',', '', $row['length'] ?? '0');
                $depth = (float) str_replace(',', '', $row['depth'] ?? '0');

                $system = CaveSystem::firstOrCreate(
                    ['name' => $systemName],
                    [
                        'slug' => Str::slug($systemName),
                        'length' => $length, // Assuming singular or system length provided
                        'vertical_range' => $depth,
                    ]
                );

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
                $cave = Cave::updateOrCreate(
                    ['name' => $name],
                    [
                        'slug' => Str::slug($name),
                        'description' => $description,
                        'cave_system_id' => $caveSystemId,
                        'location_name' => $locationName,
                        'location_country' => $row['location_country'] ?? 'United Kingdom',
                        'location_lat' => (float) ($row['latitude'] ?? 0),
                        'location_lng' => (float) ($row['longitude'] ?? 0),
                        'location_alt' => (float) ($row['altitude'] ?? 0),
                        'access_info' => $row['access_info'] ?? null,
                    ]
                );

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
}
