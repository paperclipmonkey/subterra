<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Osm\OsmCaveImporter;
use Illuminate\Console\Command;

/**
 * Sync cave entrances from OpenStreetMap via the Overpass API, for the whole
 * UK or one caving region.
 *
 * OSM data is published under the Open Database Licence (ODbL). It is kept
 * separate from registry data (see OsmCaveImporter): caves we don't hold yet
 * are created as OSM-sourced records with attribution, and caves we already
 * hold are only linked to their OSM node, never edited.
 *
 * By default only *named* entrances are imported — an unnamed `cave_entrance`
 * node is almost always a minor dig or shake hole, whereas named ones are the
 * recognised caves people actually look for. Pass --include-unnamed to widen.
 * For a hand-picked selection, use the admin "Import from OpenStreetMap" page.
 */
class SyncOsmCaves extends Command
{
    protected $signature = 'sync:osm-caves
                            {--region= : Only this caving region (e.g. "Devon", "Northern Ireland")}
                            {--bbox= : Only this box: south,west,north,east in decimal degrees}
                            {--dry-run : Show what would happen without importing}
                            {--include-unnamed : Also import entrances with no name tag}
                            {--blocklist= : Comma-separated list of cave names to always skip}';

    protected $description = 'Sync cave entrances from OpenStreetMap (Overpass API, ODbL), optionally for one region';

    public function handle(OsmCaveImporter $importer): int
    {
        $box = $this->resolveBox();
        if ($box === false) {
            return 1;
        }

        $this->info('Fetching cave entrances from OpenStreetMap (Overpass API)...');

        $entries = $importer->fetchEntrances($box, (bool) $this->option('include-unnamed'), fn ($msg) => $this->warn($msg));
        if ($entries === null) {
            $this->error('Failed to query the Overpass API on every endpoint; nothing was imported.');

            return 1;
        }

        $blocklist = array_map('strtolower', $this->getBlocklist());
        $entries = array_values(array_filter($entries, function ($entry) use ($blocklist) {
            if (in_array(strtolower($entry['name']), $blocklist, true)) {
                $this->line("<fg=gray>  ⊘ Skipped (blocklist):</> {$entry['name']}");

                return false;
            }

            return true;
        }));

        if ($entries === []) {
            $this->warn('No cave entrances found in the OSM response.');

            return 0;
        }

        $this->info('Found '.count($entries).' usable cave entrances in OSM.');

        if ($this->option('dry-run')) {
            foreach ($importer->describe($entries) as $row) {
                $this->line("  [{$row['status']}] {$row['name']} <fg=gray>(".($row['region'] ?? 'no region').')</>');
            }
            $this->info('Dry run: nothing was imported.');

            return 0;
        }

        $counts = ['created' => 0, 'linked' => 0, 'unchanged' => 0];
        foreach ($entries as $entry) {
            try {
                $result = $importer->import($entry);
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed: {$entry['name']}: {$e->getMessage()}");

                return 1;
            }

            ++$counts[$result['action']];
            $label = ['created' => '<fg=green>  ✚ New cave:</>', 'linked' => '<fg=yellow>  ↔ Linked existing cave:</>', 'unchanged' => '<fg=blue>  ⊘ Already linked:</>'][$result['action']];
            $this->line("{$label} {$entry['name']}");
        }

        $this->newLine();
        $this->info('Sync completed: '.count($entries).' processed.');
        $this->line("  <fg=green>✚ New caves:</> {$counts['created']}");
        $this->line("  <fg=yellow>↔ Linked to existing caves:</> {$counts['linked']}");
        $this->line("  <fg=blue>⊘ Already linked:</> {$counts['unchanged']}");

        return 0;
    }

    /**
     * The bounding box to query, null for the whole UK, or false on bad input.
     *
     * @return array{float, float, float, float}|false|null
     */
    private function resolveBox(): array|false|null
    {
        $region = $this->option('region');
        $bbox = $this->option('bbox');

        if ($region && $bbox) {
            $this->error('Pass either --region or --bbox, not both.');

            return false;
        }

        if ($region) {
            $key = OsmCaveImporter::regionKey((string) $region);
            if ($key === null) {
                $this->error("Unknown region '{$region}'. Known regions: ".implode(', ', array_keys(OsmCaveImporter::REGION_BOXES)).'.');

                return false;
            }

            return OsmCaveImporter::REGION_BOXES[$key];
        }

        if ($bbox) {
            $parts = array_map('trim', explode(',', (string) $bbox));
            if (count($parts) !== 4 || array_filter($parts, fn ($p) => !is_numeric($p)) !== []) {
                $this->error('--bbox must be four numbers: south,west,north,east.');

                return false;
            }
            [$south, $west, $north, $east] = array_map('floatval', $parts);
            if ($south >= $north || $west >= $east || abs($south) > 90 || abs($north) > 90) {
                $this->error('--bbox must be south,west,north,east with south < north and west < east.');

                return false;
            }

            return [$south, $north, $west, $east];
        }

        return null;
    }

    /** @return array<string> */
    private function getBlocklist(): array
    {
        $raw = (string) ($this->option('blocklist') ?? '');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
