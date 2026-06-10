<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Admin;

use App\Models\User;
use App\Services\Assistant\AssistantTool;
use App\Services\DataHealth\DataHealthService;

class ScanDataIssuesTool implements AssistantTool
{
    public function __construct(
        private readonly DataHealthService $dataHealth,
    ) {
    }

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'scan_data_issues',
                'description' => 'Scan the cave database for data-quality problems. Use issue_type="summary" first '
                    .'to get counts per issue type, then drill into a specific type to list affected records. '
                    .'Issue types: missing_length_depth (cave systems with no length or vertical range — each '
                    .'result includes measurement_hints: sentence fragments from the full description that contain '
                    .'numbers with units, ready to convert and propose), '
                    .'missing_coordinates (caves with no location), missing_region_tag (caves with no region tag, '
                    .'so they are invisible to region search), missing_description (systems with no description), '
                    .'unlinked_entrances (caves in different systems whose entrances are suspiciously close together '
                    .'— likely the same system imported as separate records).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'issue_type' => [
                            'type' => 'string',
                            'enum' => array_merge(['summary'], DataHealthService::ISSUE_TYPES),
                            'description' => 'Which issue type to scan for, or "summary" for counts of all types.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum records to return (default 25, max 50).',
                        ],
                        'offset' => [
                            'type' => 'integer',
                            'description' => 'Number of records to skip, for paging through large result sets.',
                        ],
                        'region' => [
                            'type' => 'string',
                            'description' => 'Optional region tag filter (e.g. "Mendip"), only for missing_length_depth.',
                        ],
                        'registry' => [
                            'type' => 'string',
                            'description' => 'Optional external registry filter (e.g. "mcra"), only for missing_length_depth.',
                        ],
                    ],
                    'required' => ['issue_type'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $issueType = (string) ($arguments['issue_type'] ?? 'summary');
        $limit = min(max((int) ($arguments['limit'] ?? 25), 1), 50);
        $offset = max((int) ($arguments['offset'] ?? 0), 0);
        $region = isset($arguments['region']) ? trim((string) $arguments['region']) : null;
        $registry = isset($arguments['registry']) ? trim((string) $arguments['registry']) : null;

        return match ($issueType) {
            'summary' => [
                'issue_counts' => $this->dataHealth->summary(),
                'note' => 'Call scan_data_issues again with a specific issue_type to list the affected records.',
            ],
            'missing_length_depth' => [
                'issue_type' => $issueType,
                'limit' => $limit,
                'offset' => $offset,
                'systems' => $this->dataHealth->systemsMissingLengthDepth($limit, $offset, $region ?: null, $registry ?: null),
            ],
            'missing_coordinates' => [
                'issue_type' => $issueType,
                'limit' => $limit,
                'offset' => $offset,
                'caves' => $this->dataHealth->cavesMissingCoordinates($limit, $offset),
            ],
            'missing_region_tag' => [
                'issue_type' => $issueType,
                'limit' => $limit,
                'offset' => $offset,
                'caves' => $this->dataHealth->cavesMissingRegionTag($limit, $offset),
            ],
            'missing_description' => [
                'issue_type' => $issueType,
                'limit' => $limit,
                'offset' => $offset,
                'systems' => $this->dataHealth->systemsMissingDescription($limit, $offset),
            ],
            'unlinked_entrances' => [
                'issue_type' => $issueType,
                'limit' => $limit,
                'offset' => $offset,
                'candidate_pairs' => $this->dataHealth->unlinkedEntranceCandidates($limit, $offset),
                'note' => 'Each pair lists two caves in different systems with entrances close together. '
                    .'Verify with find_link_candidates before proposing a merge or relink.',
            ],
            default => ['error' => "Unknown issue_type: {$issueType}"],
        };
    }
}
