<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\SuggestedEdit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off cleanup for artifacts left by the CCR (Cambrian) sync before
 * SyncCcrCaves decoded HTML entities and normalized line endings:
 *
 *  1. Stored cave/system text where literal characters were saved encoded
 *     (e.g. "capped &amp; gated" instead of "capped & gated").
 *  2. Bot-generated pending suggested edits whose only "change" is one of
 *     those artifacts — they render as "no differences" in the review queue.
 *
 * Safe to re-run; once everything is clean it reports zero changes.
 */
class FixCcrSyncArtifacts extends Command
{
    protected $signature = 'caves:fix-ccr-sync-artifacts {--dry-run : Report what would change without writing}';

    protected $description = 'Decode HTML-entity artifacts in CCR-imported caves and prune resulting no-op suggested edits';

    /** Cave fields written from the CCR feed. */
    private const CAVE_TEXT_FIELDS = ['name', 'description', 'location_name', 'access_info'];

    /** Cave system fields written from the CCR feed. */
    private const SYSTEM_TEXT_FIELDS = ['name', 'description', 'references'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        DB::beginTransaction();

        try {
            $caveFieldFixes = $this->decodeStoredText(
                Cave::where('registry', 'ccr')->get(),
                self::CAVE_TEXT_FIELDS,
                'Cave',
            );

            // Systems linked to CCR caves (systems carry no registry column).
            $systemIds = Cave::where('registry', 'ccr')->whereNotNull('cave_system_id')
                ->distinct()->pluck('cave_system_id');
            $systemFieldFixes = $this->decodeStoredText(
                CaveSystem::whereIn('id', $systemIds)->get(),
                self::SYSTEM_TEXT_FIELDS,
                'CaveSystem',
            );

            $editsPruned = 0;
            $editFieldsTrimmed = 0;
            $this->pruneNoOpEdits($editsPruned, $editFieldsTrimmed);

            $this->newLine();
            $this->info('Summary:');
            $this->line("  Cave fields decoded:        {$caveFieldFixes}");
            $this->line("  Cave system fields decoded: {$systemFieldFixes}");
            $this->line("  Suggested edits deleted:    {$editsPruned}");
            $this->line("  Suggested-edit fields removed (kept edit): {$editFieldsTrimmed}");

            if ($dryRun) {
                DB::rollBack();
                $this->warn('Dry run complete — rolled back.');
            } else {
                DB::commit();
                $this->info('Changes committed.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Aborted: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Decode HTML entities in the given text fields of each model, returning the
     * number of fields changed.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $models
     * @param  array<string>  $fields
     */
    private function decodeStoredText($models, array $fields, string $label): int
    {
        $changed = 0;

        foreach ($models as $model) {
            foreach ($fields as $field) {
                $value = $model->$field;
                if (!is_string($value) || $value === '') {
                    continue;
                }

                $decoded = $this->decode($value);
                if ($decoded !== $value) {
                    $this->line("  <fg=yellow>{$label} #{$model->id}</> {$field}: ".$this->preview($value).' → '.$this->preview($decoded));
                    $model->$field = $decoded;
                    ++$changed;
                }
            }

            if ($model->isDirty()) {
                $model->save();
            }
        }

        return $changed;
    }

    /**
     * Remove no-op fields from pending bot-generated suggested edits, deleting
     * the edit entirely when nothing real remains.
     */
    private function pruneNoOpEdits(int &$deleted, int &$fieldsTrimmed): void
    {
        $edits = SuggestedEdit::where('status', 'pending')
            ->whereNull('user_id')
            ->whereIn('suggestable_type', [Cave::class, CaveSystem::class])
            ->get();

        foreach ($edits as $edit) {
            $original = $edit->original_data ?? [];
            $suggested = $edit->suggested_data ?? [];

            $realOriginal = [];
            $realSuggested = [];
            $trimmedHere = 0;

            foreach ($suggested as $key => $newValue) {
                $oldValue = $original[$key] ?? null;
                if ($this->equivalent($oldValue, $newValue)) {
                    ++$trimmedHere;
                    continue; // no-op artifact — drop this field
                }
                $realSuggested[$key] = $newValue;
                $realOriginal[$key] = $oldValue;
            }

            if (empty($realSuggested)) {
                $this->line("  <fg=red>Delete edit #{$edit->id}</> (".$edit->suggestable_type.' #'.$edit->suggestable_id.') — all fields were no-ops');
                $edit->delete();
                ++$deleted;
            } elseif ($trimmedHere > 0) {
                $this->line("  <fg=yellow>Trim edit #{$edit->id}</> — removed {$trimmedHere} no-op field(s), ".count($realSuggested).' real change(s) kept');
                $edit->update([
                    'original_data' => $realOriginal,
                    'suggested_data' => $realSuggested,
                ]);
                $fieldsTrimmed += $trimmedHere;
            }
        }
    }

    /** Two values are equivalent once entities are decoded and line endings normalized. */
    private function equivalent($a, $b): bool
    {
        return $this->canonical($a) === $this->canonical($b);
    }

    private function canonical($value): string
    {
        if (!is_string($value)) {
            return (string) $value;
        }

        return str_replace("\r\n", "\n", $this->decode($value));
    }

    private function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
    }

    private function preview(string $value): string
    {
        $value = str_replace(["\r\n", "\n"], '⏎', $value);

        return '"'.(mb_strlen($value) > 60 ? mb_substr($value, 0, 57).'…' : $value).'"';
    }
}
