<?php

declare(strict_types=1);

namespace App\Services\DataHealth;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\SuggestedEdit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Creates AI-attributed SuggestedEdit rows. This is the ONLY write path the
 * data-steward tools have — nothing is applied until an admin approves the
 * suggestion in the existing review UI.
 */
class ProposalService
{
    public const SOURCE_PIP = 'pip';

    public function newBatchId(): string
    {
        return 'pip-'.Str::uuid()->toString();
    }

    /**
     * Propose plain field changes on a Cave or CaveSystem.
     *
     * @param  array<string, mixed>  $changes  field => new value
     */
    public function proposeFieldChanges(
        Model $target,
        array $changes,
        string $reasoning,
        User $user,
        ?string $batchId = null
    ): SuggestedEdit {
        $original = [];
        foreach (array_keys($changes) as $field) {
            $original[$field] = $target->getRawOriginal($field);
        }

        return SuggestedEdit::create([
            'user_id' => $user->id,
            'suggestable_type' => $target::class,
            'suggestable_id' => $target->getKey(),
            'original_data' => $original,
            'suggested_data' => $changes,
            'status' => 'pending',
            'source' => self::SOURCE_PIP,
            'batch_id' => $batchId,
            'reasoning' => $reasoning,
        ]);
    }

    /**
     * Propose adding/removing tags on a Cave or CaveSystem.
     *
     * @param  array<int, \App\Models\Tag>  $tagsAdd
     * @param  array<int, \App\Models\Tag>  $tagsRemove
     */
    public function proposeTagChanges(
        Cave|CaveSystem $target,
        array $tagsAdd,
        array $tagsRemove,
        string $reasoning,
        User $user,
        ?string $batchId = null
    ): SuggestedEdit {
        $currentTags = $target->tags()->get();

        return SuggestedEdit::create([
            'user_id' => $user->id,
            'suggestable_type' => $target::class,
            'suggestable_id' => $target->getKey(),
            'original_data' => [
                'tags' => $currentTags->pluck('tag')->values()->all(),
            ],
            'suggested_data' => array_filter([
                'tags_add' => collect($tagsAdd)->pluck('id')->values()->all(),
                'tags_add_names' => collect($tagsAdd)->pluck('tag')->values()->all(),
                'tags_remove' => collect($tagsRemove)->pluck('id')->values()->all(),
                'tags_remove_names' => collect($tagsRemove)->pluck('tag')->values()->all(),
            ], fn ($v) => $v !== []),
            'status' => 'pending',
            'source' => self::SOURCE_PIP,
            'batch_id' => $batchId,
            'reasoning' => $reasoning,
        ]);
    }

    /**
     * Propose merging $source into $target (caves, routes, trips, files, tags
     * all migrate to $target; $source is deleted). Applied on approval via
     * CaveSystemMergeService.
     */
    public function proposeSystemMerge(
        CaveSystem $target,
        CaveSystem $source,
        string $reasoning,
        User $user,
        ?string $batchId = null
    ): SuggestedEdit {
        return SuggestedEdit::create([
            'user_id' => $user->id,
            'suggestable_type' => CaveSystem::class,
            'suggestable_id' => $target->id,
            'original_data' => [
                'merge_source_system_id' => null,
                'target_entrances' => $target->caves()->pluck('name')->values()->all(),
                'source_entrances' => $source->caves()->pluck('name')->values()->all(),
            ],
            'suggested_data' => [
                'merge_source_system_id' => $source->id,
                'merge_source_system_name' => $source->name,
            ],
            'status' => 'pending',
            'source' => self::SOURCE_PIP,
            'batch_id' => $batchId,
            'reasoning' => $reasoning,
        ]);
    }
}
