<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Admin;

use App\Models\CaveSystem;
use App\Models\User;
use App\Services\Assistant\AssistantTool;
use App\Services\DataHealth\ProposalService;

class ProposeSystemMergeTool implements AssistantTool
{
    public function __construct(
        private readonly ProposalService $proposals,
    ) {
    }

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'propose_system_merge',
                'description' => 'Propose merging one cave system into another (for caves that should be linked as '
                    .'entrances of the same system but were imported as separate records). On approval, all caves, '
                    .'routes, trips, files and tags move to the target system and the source system is deleted. '
                    .'This does NOT change live data — it files a suggested edit for admin approval. Verify the '
                    .'pairing with find_link_candidates first, and put the evidence (distance, name match, '
                    .'description references) in reasoning. The target should be the better-documented system.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'target_system_id' => [
                            'type' => 'integer',
                            'description' => 'The system to KEEP. Everything merges into this one.',
                        ],
                        'source_system_id' => [
                            'type' => 'integer',
                            'description' => 'The system to merge away. It is deleted after its records move to the target.',
                        ],
                        'reasoning' => [
                            'type' => 'string',
                            'description' => 'Evidence that these are the same system (entrance distance, name similarity, description references).',
                        ],
                        'batch_id' => [
                            'type' => 'string',
                            'description' => 'Optional: reuse a batch_id from an earlier proposal this turn to group related fixes.',
                        ],
                    ],
                    'required' => ['target_system_id', 'source_system_id', 'reasoning'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $targetId = (int) ($arguments['target_system_id'] ?? 0);
        $sourceId = (int) ($arguments['source_system_id'] ?? 0);
        $reasoning = trim((string) ($arguments['reasoning'] ?? ''));
        $batchId = isset($arguments['batch_id']) ? trim((string) $arguments['batch_id']) : null;

        if ($reasoning === '') {
            return ['error' => 'reasoning is required — the reviewing admin needs the evidence for the merge.'];
        }

        if ($targetId === $sourceId) {
            return ['error' => 'target_system_id and source_system_id must differ.'];
        }

        $target = CaveSystem::withCount('caves')->find($targetId);
        $source = CaveSystem::withCount('caves')->find($sourceId);

        if (!$target || !$source) {
            return ['error' => 'Cave system not found: '.(!$target ? $targetId : $sourceId)];
        }

        $duplicate = \App\Models\SuggestedEdit::where('status', 'pending')
            ->where('suggestable_type', CaveSystem::class)
            ->where('suggestable_id', $target->id)
            ->where('suggested_data->merge_source_system_id', $source->id)
            ->exists();
        if ($duplicate) {
            return ['error' => 'A pending merge proposal for these two systems already exists.'];
        }

        $edit = $this->proposals->proposeSystemMerge($target, $source, $reasoning, $user, $batchId ?: null);

        return [
            'success' => true,
            'suggested_edit_id' => $edit->id,
            'batch_id' => $edit->batch_id,
            'keep' => ['id' => $target->id, 'name' => $target->name, 'entrances' => $target->caves_count],
            'merge_away' => ['id' => $source->id, 'name' => $source->name, 'entrances' => $source->caves_count],
            'review_url' => "/admin/suggested-edits/{$edit->id}",
            'note' => 'Merge proposal filed. Nothing changes until an admin approves it.',
        ];
    }
}
