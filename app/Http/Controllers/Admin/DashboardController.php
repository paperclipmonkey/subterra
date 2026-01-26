<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiInteraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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

        // Map interactions to results
        $popularRecords = $interactions->map(function ($interaction) use ($modelCache) {
            $model = $modelCache[$interaction->trackable_type][$interaction->trackable_id] ?? null;
            
            if (!$model) {
                return null;
            }

            // Get sparkline data (last 30 days)
            $sparklineData = $this->getSparklineData(
                $interaction->trackable_type,
                $interaction->trackable_id
            );

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

        return response()->json([
            'data' => $popularRecords,
        ]);
    }

    /**
     * Get sparkline data for the last 30 days.
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
        for ($i = 29; $i >= 0; $i--) {
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
        if (method_exists($model, 'getAttribute')) {
            return $model->getAttribute('name') ?? $model->getAttribute('title') ?? $model->getAttribute('slug') ?? 'Unknown';
        }
        
        return 'Unknown';
    }
}
