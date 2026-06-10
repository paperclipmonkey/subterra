<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Admin;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use App\Models\User;
use App\Services\Assistant\AssistantTool;
use App\Services\DataHealth\ProposalService;

class ProposeBulkTagTool implements AssistantTool
{
    private const MAX_TARGETS = 100;

    public function __construct(
        private readonly ProposalService $proposals,
    ) {
    }

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'propose_bulk_tag',
                'description' => 'Propose adding and/or removing tags on many caves and/or cave systems in one go '
                    .'(e.g. add the "Curated" tag to a list of popular trips). This does NOT change live data — it '
                    .'files one suggested edit per target, grouped under a single batch so the admin can approve '
                    .'them all with one click. Call list_tags first and pass exact tag IDs. Confirm the target '
                    .'list with the admin in chat BEFORE calling this.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'cave_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'IDs of caves to apply the tag changes to.',
                        ],
                        'cave_system_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'IDs of cave systems to apply the tag changes to.',
                        ],
                        'add_tag_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Tag IDs (from list_tags) to add to every target.',
                        ],
                        'remove_tag_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Tag IDs (from list_tags) to remove from every target.',
                        ],
                        'reasoning' => [
                            'type' => 'string',
                            'description' => 'Why these targets should get these tag changes — shown to the reviewing admin.',
                        ],
                    ],
                    'required' => ['reasoning'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $caveIds = array_values(array_unique(array_map('intval', (array) ($arguments['cave_ids'] ?? []))));
        $systemIds = array_values(array_unique(array_map('intval', (array) ($arguments['cave_system_ids'] ?? []))));
        $addTagIds = array_values(array_unique(array_map('intval', (array) ($arguments['add_tag_ids'] ?? []))));
        $removeTagIds = array_values(array_unique(array_map('intval', (array) ($arguments['remove_tag_ids'] ?? []))));
        $reasoning = trim((string) ($arguments['reasoning'] ?? ''));

        if ($caveIds === [] && $systemIds === []) {
            return ['error' => 'Provide at least one of cave_ids or cave_system_ids.'];
        }

        if ($addTagIds === [] && $removeTagIds === []) {
            return ['error' => 'Provide at least one of add_tag_ids or remove_tag_ids.'];
        }

        if ($reasoning === '') {
            return ['error' => 'reasoning is required — the reviewing admin needs to know why.'];
        }

        if (count($caveIds) + count($systemIds) > self::MAX_TARGETS) {
            return ['error' => 'Too many targets — maximum '.self::MAX_TARGETS.' per call. Split into multiple calls.'];
        }

        $addTags = Tag::whereKey($addTagIds)->get();
        $removeTags = Tag::whereKey($removeTagIds)->get();

        $missingTagIds = array_merge(
            array_diff($addTagIds, $addTags->pluck('id')->all()),
            array_diff($removeTagIds, $removeTags->pluck('id')->all())
        );
        if ($missingTagIds !== []) {
            return ['error' => 'Unknown tag IDs: '.implode(', ', $missingTagIds).'. Call list_tags to see valid IDs.'];
        }

        $unassignable = $addTags->reject(fn (Tag $tag) => $tag->assignable);
        if ($unassignable->isNotEmpty()) {
            return ['error' => 'These tags are not assignable: '.$unassignable->pluck('tag')->implode(', ')];
        }

        $caves = Cave::whereKey($caveIds)->get();
        $systems = CaveSystem::whereKey($systemIds)->get();

        $missingTargets = array_merge(
            array_diff($caveIds, $caves->pluck('id')->all()),
            array_diff($systemIds, $systems->pluck('id')->all())
        );
        if ($missingTargets !== []) {
            return ['error' => 'Unknown target IDs: '.implode(', ', $missingTargets)];
        }

        $batchId = $this->proposals->newBatchId();
        $created = [];
        $skipped = [];

        foreach ($caves->concat($systems) as $target) {
            $currentTagIds = $target->tags()->pluck('tags.id')->all();

            // Only propose changes that aren't already true for this target
            $effectiveAdd = $addTags->reject(fn (Tag $tag) => in_array($tag->id, $currentTagIds, true))->values()->all();
            $effectiveRemove = $removeTags->filter(fn (Tag $tag) => in_array($tag->id, $currentTagIds, true))->values()->all();

            if ($effectiveAdd === [] && $effectiveRemove === []) {
                $skipped[] = $target->name;
                continue;
            }

            $edit = $this->proposals->proposeTagChanges($target, $effectiveAdd, $effectiveRemove, $reasoning, $user, $batchId);
            $created[] = [
                'suggested_edit_id' => $edit->id,
                'target' => $target->name,
                'target_type' => $target instanceof Cave ? 'cave' : 'cave_system',
            ];
        }

        if ($created === []) {
            return [
                'success' => false,
                'note' => 'Every target already has the requested tag state — nothing to propose.',
                'skipped' => $skipped,
            ];
        }

        return [
            'success' => true,
            'batch_id' => $batchId,
            'proposals_created' => count($created),
            'targets' => array_column($created, 'target'),
            'skipped_already_correct' => $skipped,
            'review_url' => '/admin/suggested-edits?batch='.$batchId,
            'note' => 'Proposals filed as one batch. Nothing changes until an admin approves the batch in the review queue.',
        ];
    }
}
