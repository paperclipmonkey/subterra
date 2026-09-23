<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Osm\OsmCaveImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Curated import from OpenStreetMap: preview the named cave entrances OSM has
 * in a region, then import only the ones an admin picks.
 */
class OsmImportController extends Controller
{
    /** Raw Overpass results are cached briefly so browsing a region doesn't hammer the public API. */
    private const CACHE_SECONDS = 600;

    private const MAX_IMPORT = 100;

    public function __construct(private readonly OsmCaveImporter $importer)
    {
    }

    public function regions(): JsonResponse
    {
        return response()->json(['data' => array_keys(OsmCaveImporter::REGION_BOXES)]);
    }

    public function candidates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'region' => ['required', 'string', Rule::in(array_keys(OsmCaveImporter::REGION_BOXES))],
        ]);
        $region = $validated['region'];

        $entries = Cache::get($this->cacheKey($region));
        if ($entries === null) {
            $entries = $this->importer->fetchEntrances(OsmCaveImporter::REGION_BOXES[$region]);
            if ($entries === null) {
                return response()->json(['message' => 'OpenStreetMap (Overpass) is not responding. Please try again in a minute.'], 502);
            }
            Cache::put($this->cacheKey($region), $entries, self::CACHE_SECONDS);
        }

        // Statuses are recomputed every time: they depend on our own data.
        $rows = $this->importer->describe($entries);
        usort($rows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return response()->json([
            'data' => $rows,
            'attribution' => 'Cave data © OpenStreetMap contributors, available under the Open Database Licence (ODbL).',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'node_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IMPORT],
            'node_ids.*' => ['required', 'string', 'regex:/^\d{1,15}$/', 'distinct'],
        ]);

        // Re-fetch the chosen nodes from OSM rather than trusting client-sent data.
        $entries = $this->importer->fetchNodes($validated['node_ids']);
        if ($entries === null) {
            return response()->json(['message' => 'OpenStreetMap (Overpass) is not responding. Nothing was imported; please try again.'], 502);
        }

        $byId = collect($entries)->keyBy('osm_id');
        $results = [];

        foreach ($validated['node_ids'] as $id) {
            $entry = $byId->get($id);
            if ($entry === null) {
                // Deleted in OSM, or no longer tagged as a named cave entrance.
                $results[] = ['osm_id' => $id, 'action' => 'not_found', 'cave' => null];
                continue;
            }

            $result = $this->importer->import($entry);
            $results[] = [
                'osm_id' => $id,
                'action' => $result['action'],
                'cave' => ['id' => $result['cave']->id, 'name' => $result['cave']->name, 'slug' => $result['cave']->slug],
            ];
        }

        Log::info('OSM curated import', [
            'user_id' => $request->user()?->id,
            'counts' => array_count_values(array_column($results, 'action')),
        ]);

        return response()->json(['data' => $results]);
    }

    private function cacheKey(string $region): string
    {
        return 'osm-candidates:'.md5($region);
    }
}
