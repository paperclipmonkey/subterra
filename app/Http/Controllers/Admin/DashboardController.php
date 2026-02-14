<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiInteraction;
use App\Models\Callout;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Get the most popular records across the system.
     */
    public function popularRecords(): JsonResponse
    {
        $interactions = ApiInteraction::select(
            'trackable_type',
            'trackable_id',
            DB::raw('COUNT(*) as total_interactions')
        )
        ->groupBy('trackable_type', 'trackable_id')
        ->orderBy('total_interactions', 'desc')
        ->limit(10)
        ->get();

        // Group interactions by type for efficient querying
        $interactionsByType = $interactions->groupBy('trackable_type');

        // Fetch all models in bulk queries
        $modelCache = [];
        foreach ($interactionsByType as $type => $typeInteractions) {
            $ids = $typeInteractions->pluck('trackable_id')->toArray();
            if (class_exists($type)) {
                $modelCache[$type] = $type::whereIn('id', $ids)->get()->keyBy('id');
            }
        }

        // Fetch all sparkline data in a single query
        $thirtyDaysAgo = now()->subDays(30);
        $typeIdPairs = $interactions->map(function ($interaction) {
            return [
                'type' => $interaction->trackable_type,
                'id' => $interaction->trackable_id,
            ];
        });

        // Build sparkline cache
        $sparklineCache = $this->getSparklineDataBulk($typeIdPairs, $thirtyDaysAgo);

        // Map interactions to results
        $popularRecords = $interactions->map(function ($interaction) use ($modelCache, $sparklineCache) {
            $model = $modelCache[$interaction->trackable_type][$interaction->trackable_id] ?? null;

            if (!$model) {
                // Log missing models to help identify data integrity issues
                \Log::warning('API interaction tracked for missing model', [
                    'trackable_type' => $interaction->trackable_type,
                    'trackable_id' => $interaction->trackable_id,
                    'total_interactions' => $interaction->total_interactions,
                ]);

                return;
            }

            $cacheKey = $interaction->trackable_type.':'.$interaction->trackable_id;
            $sparklineData = $sparklineCache[$cacheKey] ?? array_fill(0, 30, 0);

            return [
                'type' => class_basename($interaction->trackable_type),
                'id' => $interaction->trackable_id,
                'identifier' => $model->getRouteKey(),
                'name' => $this->getModelName($model),
                'total_interactions' => $interaction->total_interactions,
                'sparkline' => $sparklineData,
            ];
        })
        ->filter() // Remove null entries
        ->values();

        // Generate labels for the last 30 days
        $labels = [];
        for ($i = 29; $i >= 0; --$i) {
            $labels[] = now()->subDays($i)->format('Y-m-d');
        }

        return response()->json([
            'labels' => $labels,
            'data' => $popularRecords,
        ]);
    }

    /**
     * Get sparkline data for multiple records in bulk.
     */
    private function getSparklineDataBulk($typeIdPairs, $thirtyDaysAgo): array
    {
        // Fetch all interactions for all type/id pairs in a single query
        $allInteractions = ApiInteraction::where('created_at', '>=', $thirtyDaysAgo)
            ->where(function ($query) use ($typeIdPairs) {
                foreach ($typeIdPairs as $pair) {
                    $query->orWhere(function ($q) use ($pair) {
                        $q->where('trackable_type', $pair['type'])
                          ->where('trackable_id', $pair['id']);
                    });
                }
            })
            ->select(
                'trackable_type',
                'trackable_id',
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('trackable_type', 'trackable_id', 'date')
            ->orderBy('date')
            ->get();

        // Organize data by type:id
        $dailyCountsByRecord = [];
        foreach ($allInteractions as $interaction) {
            $key = $interaction->trackable_type.':'.$interaction->trackable_id;
            $dailyCountsByRecord[$key][$interaction->date] = $interaction->count;
        }

        // Generate sparklines with missing days filled in
        $sparklineCache = [];
        foreach ($typeIdPairs as $pair) {
            $key = $pair['type'].':'.$pair['id'];
            $dailyCounts = $dailyCountsByRecord[$key] ?? [];

            $sparkline = [];
            for ($i = 29; $i >= 0; --$i) {
                $date = now()->subDays($i)->format('Y-m-d');
                $sparkline[] = $dailyCounts[$date] ?? 0;
            }

            $sparklineCache[$key] = $sparkline;
        }

        return $sparklineCache;
    }

    /**
     * Get sparkline data for the last 30 days (single record - deprecated, kept for backward compatibility).
     */
    private function getSparklineData(string $type, int $id): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        // Get daily counts for the last 30 days
        $dailyCounts = ApiInteraction::where('trackable_type', $type)
            ->where('trackable_id', $id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Fill in missing days with 0
        $sparkline = [];
        for ($i = 29; $i >= 0; --$i) {
            $date = now()->subDays($i)->format('Y-m-d');
            $sparkline[] = $dailyCounts[$date] ?? 0;
        }

        return $sparkline;
    }

    /**
     * Get a display name for the model.
     */
    private function getModelName($model): string
    {
        return $model->name ?? $model->title ?? $model->slug ?? 'Unknown';
    }

    /**
     * Get growth metrics (Callouts, Trips, Users) for the last 30 days.
     */
    public function metricsOverview(): JsonResponse
    {
        $thirtyDaysAgo = now()->subDays(30);
        $labels = [];
        for ($i = 29; $i >= 0; --$i) {
            $labels[] = now()->subDays($i)->format('Y-m-d');
        }

        $metrics = [
            'Callouts' => ['model' => Callout::class, 'column' => 'created_at'],
            'Trips' => ['model' => Trip::class, 'column' => 'created_at'],
            'Users' => ['model' => User::class, 'column' => 'created_at'],
        ];

        $data = [];

        foreach ($metrics as $label => $config) {
            $modelClass = $config['model'];
            $column = $config['column'];

            $counts = $modelClass::where($column, '>=', $thirtyDaysAgo)
                ->select(DB::raw("DATE($column) as date"), DB::raw('COUNT(*) as count'))
                ->groupBy('date')
                ->pluck('count', 'date')
                ->toArray();

            $sparkline = [];
            foreach ($labels as $date) {
                $sparkline[] = $counts[$date] ?? 0;
            }

            $data[] = [
                'label' => $label,
                'sparkline' => $sparkline,
            ];
        }

        return response()->json([
            'labels' => $labels,
            'data' => $data,
        ]);
    }
}
