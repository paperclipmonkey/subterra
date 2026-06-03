<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cave;

/**
 * Resolves the region-specific cave-rescue / 999 information for a cave, based on its
 * "region" tag, for the duty officer's incident Rescue Protocol script.
 */
class CaveRescueService
{
    /**
     * @return array{region: ?string, police_force: ?string, rescue_team: string, rescue_abbr: ?string, note: ?string, procedure: array<int, string>}
     */
    public function forCave(?Cave $cave): array
    {
        $regions = config('cave_rescue.regions', []);
        $regionName = null;
        $resolved = null;

        if ($cave) {
            $cave->loadMissing('tags');
            foreach ($cave->tags as $tag) {
                if ($tag->category === 'region' && isset($regions[$tag->tag])) {
                    $regionName = $tag->tag;
                    $resolved = $regions[$tag->tag];
                    break;
                }
            }
        }

        $resolved ??= config('cave_rescue.default');

        return [
            'region' => $regionName,
            'police_force' => $resolved['police_force'],
            'rescue_team' => $resolved['rescue_team'],
            'rescue_abbr' => $resolved['rescue_abbr'],
            'note' => $resolved['note'],
            'procedure' => config('cave_rescue.procedure'),
        ];
    }
}
