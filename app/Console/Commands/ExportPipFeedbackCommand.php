<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PipFeedback;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Dump flagged (thumbs-down) Pip conversations as a markdown report you can
 * read through to spot bad responses and improve the system prompt / tools.
 *
 * Examples:
 *   php artisan pip:export-feedback
 *   php artisan pip:export-feedback --include-positive --limit=50
 *   php artisan pip:export-feedback --output=storage/app/pip-flagged.md
 *   php artisan pip:export-feedback --unreviewed-only
 */
class ExportPipFeedbackCommand extends Command
{
    protected $signature = 'pip:export-feedback
        {--include-positive : Include thumbs-up rows too (default: thumbs-down only).}
        {--unreviewed-only  : Only include rows that have not been marked reviewed.}
        {--limit=           : Limit the number of rows.}
        {--output=          : Path to write the markdown report (default: storage/app/pip-feedback-<timestamp>.md).}';

    protected $description = 'Export Pip thumbs-down conversations as a markdown report for review.';

    public function handle(): int
    {
        $query = PipFeedback::with('user:id,name,email')
            ->orderByDesc('created_at');

        if (!$this->option('include-positive')) {
            $query->where('rating', '<', 0);
        }

        if ($this->option('unreviewed-only')) {
            $query->where('reviewed', false);
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            $this->info('No feedback rows matched.');

            return self::SUCCESS;
        }

        $output = $this->option('output')
            ?: storage_path('app/pip-feedback-'.now()->format('Y-m-d_His').'.md');

        $dir = dirname($output);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0o755, true);
        }

        $md = $this->renderMarkdown($items);
        File::put($output, $md);

        $this->info("Wrote {$items->count()} feedback rows to {$output}");

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PipFeedback>  $items
     */
    private function renderMarkdown($items): string
    {
        $lines = [];
        $lines[] = '# Pip Feedback Review';
        $lines[] = '';
        $lines[] = 'Generated: '.now()->toDateTimeString();
        $lines[] = 'Rows: '.$items->count();
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';

        foreach ($items as $f) {
            $rating = $f->rating > 0 ? '👍 thumbs up' : '👎 thumbs down';
            $user = $f->user ? "{$f->user->name} <{$f->user->email}>" : 'Anonymous';
            $reviewed = $f->reviewed ? ' — _reviewed_' : '';

            $lines[] = "## Feedback #{$f->id} — {$rating}{$reviewed}";
            $lines[] = '';
            $lines[] = "- **User**: {$user}";
            $lines[] = '- **Created**: '.$f->created_at->toDateTimeString();
            if ($f->comment) {
                $lines[] = '- **Comment**: '.$f->comment;
            }
            $lines[] = '';
            $lines[] = '### Transcript';
            $lines[] = '';

            $transcript = is_array($f->transcript) ? $f->transcript : [];
            foreach ($transcript as $m) {
                $role = strtoupper((string) ($m['role'] ?? 'unknown'));
                $content = trim((string) ($m['content'] ?? ''));
                $lines[] = "**{$role}:**";
                $lines[] = '';
                // Quote-block the content so embedded markdown doesn't escape the section.
                foreach (preg_split("/\r\n|\n|\r/", $content) as $line) {
                    $lines[] = '> '.$line;
                }
                $lines[] = '';
            }

            $lines[] = '---';
            $lines[] = '';
        }

        return implode("\n", $lines)."\n";
    }
}
