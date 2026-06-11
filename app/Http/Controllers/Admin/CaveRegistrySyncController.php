<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncCaveRegistryJob;
use Illuminate\Http\JsonResponse;

class CaveRegistrySyncController extends Controller
{
    private const REGISTRIES = ['mcra', 'fod', 'gsg', 'cncc', 'pdc', 'ccr'];

    public function dispatch(string $registry): JsonResponse
    {
        if (!in_array($registry, self::REGISTRIES, true)) {
            return response()->json(['message' => 'Unknown registry.'], 422);
        }

        SyncCaveRegistryJob::dispatch($registry);

        return response()->json(['message' => "Sync for registry '{$registry}' has been queued."], 202);
    }
}
