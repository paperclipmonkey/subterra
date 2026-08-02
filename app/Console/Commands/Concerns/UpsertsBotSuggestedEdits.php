<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Models\SuggestedEdit;

/**
 * Create or update the sync-owned pending suggested edit for a model.
 *
 * Registry syncs must never touch community-submitted pending edits: only the
 * bot's own edit (user_id IS NULL) is looked up and updated, and new fields
 * are merged into it rather than wholesale replacing previously suggested
 * data. A user-owned pending edit simply coexists with the bot's edit.
 */
trait UpsertsBotSuggestedEdits
{
    /**
     * @param  array<string, mixed>  $originalData
     * @param  array<string, mixed>  $suggestedData
     */
    protected function upsertBotSuggestedEdit(string $suggestableType, int $suggestableId, array $originalData, array $suggestedData): SuggestedEdit
    {
        $existingBotEdit = SuggestedEdit::whereNull('user_id')
            ->where('suggestable_type', $suggestableType)
            ->where('suggestable_id', $suggestableId)
            ->where('status', 'pending')
            ->first();

        if ($existingBotEdit) {
            $existingBotEdit->update([
                'original_data' => array_merge($existingBotEdit->original_data ?? [], $originalData),
                'suggested_data' => array_merge($existingBotEdit->suggested_data ?? [], $suggestedData),
            ]);

            return $existingBotEdit;
        }

        return SuggestedEdit::create([
            'user_id' => null,
            'suggestable_type' => $suggestableType,
            'suggestable_id' => $suggestableId,
            'original_data' => $originalData,
            'suggested_data' => $suggestedData,
            'status' => 'pending',
        ]);
    }
}
