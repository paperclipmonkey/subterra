<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Admin;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\User;
use App\Services\Assistant\AssistantTool;
use App\Services\DataHealth\ProposalService;

class ProposeDataFixTool implements AssistantTool
{
    /** Fields the AI is allowed to propose changes to, per target type. */
    private const ALLOWED_FIELDS = [
        'cave' => [
            'name', 'description', 'access_info', 'location_name', 'location_country',
            'location_lat', 'location_lng', 'location_alt', 'length', 'depth', 'cave_system_id',
        ],
        'cave_system' => [
            'name', 'description', 'length', 'vertical_range', 'references',
        ],
    ];

    private const NUMERIC_FIELDS = [
        'location_lat', 'location_lng', 'location_alt', 'length', 'depth',
        'vertical_range', 'cave_system_id',
    ];

    public function __construct(
        private readonly ProposalService $proposals,
    ) {
    }

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'propose_data_fix',
                'description' => 'Propose field changes to a cave or cave system. This does NOT change live data — '
                    .'it files a suggested edit for an admin to approve in the review queue. Cave fields: name, '
                    .'description, access_info, location_name, location_country, location_lat, location_lng, '
                    .'location_alt, length, depth, cave_system_id (relink an entrance to a different system). '
                    .'Cave system fields: name, description, length (metres), vertical_range (metres), references. '
                    .'Only propose values you have evidence for (tool results, the record description, or values '
                    .'the admin gave you in chat). Always include where the value came from in reasoning.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'target_type' => [
                            'type' => 'string',
                            'enum' => ['cave', 'cave_system'],
                            'description' => 'Whether the fix targets an individual cave (entrance) or a cave system.',
                        ],
                        'target_id' => [
                            'type' => 'integer',
                            'description' => 'Numeric ID of the cave or cave system.',
                        ],
                        'changes' => [
                            'type' => 'object',
                            'description' => 'Map of field name to proposed new value, e.g. {"length": 4500, "vertical_range": 120}.',
                        ],
                        'reasoning' => [
                            'type' => 'string',
                            'description' => 'Evidence for the change, shown to the reviewing admin. Cite the source '
                                .'(e.g. "description states \'4.5km of passage\'", or "admin provided value in chat").',
                        ],
                        'batch_id' => [
                            'type' => 'string',
                            'description' => 'Optional: reuse a batch_id from an earlier proposal this turn to group related fixes for one-click review.',
                        ],
                    ],
                    'required' => ['target_type', 'target_id', 'changes', 'reasoning'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $targetType = (string) ($arguments['target_type'] ?? '');
        $targetId = (int) ($arguments['target_id'] ?? 0);
        $changes = is_array($arguments['changes'] ?? null) ? $arguments['changes'] : [];
        $reasoning = trim((string) ($arguments['reasoning'] ?? ''));
        $batchId = isset($arguments['batch_id']) ? trim((string) $arguments['batch_id']) : null;

        if (!isset(self::ALLOWED_FIELDS[$targetType])) {
            return ['error' => 'target_type must be "cave" or "cave_system".'];
        }

        if ($changes === []) {
            return ['error' => 'changes must contain at least one field.'];
        }

        if ($reasoning === '') {
            return ['error' => 'reasoning is required — the reviewing admin needs to know where the value came from.'];
        }

        $disallowed = array_diff(array_keys($changes), self::ALLOWED_FIELDS[$targetType]);
        if ($disallowed !== []) {
            return [
                'error' => 'These fields cannot be proposed for a '.$targetType.': '.implode(', ', $disallowed),
                'allowed_fields' => self::ALLOWED_FIELDS[$targetType],
            ];
        }

        foreach ($changes as $field => $value) {
            if (in_array($field, self::NUMERIC_FIELDS, true) && $value !== null && !is_numeric($value)) {
                return ['error' => "Field {$field} must be numeric, got: ".json_encode($value)];
            }
        }

        $target = $targetType === 'cave'
            ? Cave::find($targetId)
            : CaveSystem::find($targetId);

        if (!$target) {
            return ['error' => ucfirst(str_replace('_', ' ', $targetType))." with ID {$targetId} not found."];
        }

        if (isset($changes['cave_system_id']) && !CaveSystem::whereKey((int) $changes['cave_system_id'])->exists()) {
            return ['error' => 'Proposed cave_system_id '.$changes['cave_system_id'].' does not exist.'];
        }

        // Drop no-op changes so the reviewer only sees a real diff
        $effective = array_filter(
            $changes,
            fn ($value, $field) => $target->getRawOriginal($field) != $value,
            ARRAY_FILTER_USE_BOTH
        );

        if ($effective === []) {
            return ['error' => 'Every proposed value already matches the current data — nothing to suggest.'];
        }

        $edit = $this->proposals->proposeFieldChanges($target, $effective, $reasoning, $user, $batchId ?: null);

        return [
            'success' => true,
            'suggested_edit_id' => $edit->id,
            'batch_id' => $edit->batch_id,
            'target' => $target->name,
            'changes' => $effective,
            'review_url' => "/admin/suggested-edits/{$edit->id}",
            'note' => 'Proposal filed. It will only take effect once an admin approves it in the review queue.',
        ];
    }
}
