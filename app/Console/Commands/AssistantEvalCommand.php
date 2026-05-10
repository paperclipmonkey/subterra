<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AssistantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Run a fixed dataset of prompts through the AI assistant and write a markdown
 * report. Use this to baseline behaviour and iterate on the system prompt /
 * tools — rate each response inline in the report and share it back.
 *
 * Usage:
 *   php artisan assistant:eval
 *   php artisan assistant:eval --filter=accommodation
 *   php artisan assistant:eval --user=42 --dataset=tests/AssistantEval/dataset.json
 */
class AssistantEvalCommand extends Command
{
    protected $signature = 'assistant:eval
        {--user=             : ID of the user to run as. Defaults to first platform_admin.}
        {--filter=           : Only run prompts whose category or id matches this string.}
        {--dataset=          : Path to a JSON dataset (default: tests/AssistantEval/dataset.json).}
        {--output=           : Path to write the markdown report (default: tests/AssistantEval/results/<timestamp>.md).}
        {--limit=            : Stop after this many prompts. Useful for smoke-testing.}';

    protected $description = 'Run a prompt dataset through the assistant and write a markdown report.';

    public function handle(AssistantService $service): int
    {
        $datasetPath = $this->option('dataset')
            ?: base_path('tests/AssistantEval/dataset.json');

        if (!File::exists($datasetPath)) {
            $this->error("Dataset not found: {$datasetPath}");

            return self::FAILURE;
        }

        $raw = File::get($datasetPath);
        $dataset = json_decode($raw, true);

        if (!is_array($dataset)) {
            $this->error("Dataset is not a valid JSON array: {$datasetPath}");

            return self::FAILURE;
        }

        // Filter by category or id substring
        $filter = (string) $this->option('filter');
        if ($filter !== '') {
            $dataset = array_values(array_filter(
                $dataset,
                fn ($p) => str_contains((string) ($p['category'] ?? ''), $filter)
                    || str_contains((string) ($p['id'] ?? ''), $filter)
            ));

            if (empty($dataset)) {
                $this->warn("No prompts matched filter '{$filter}'.");

                return self::SUCCESS;
            }
        }

        if ($limit = (int) $this->option('limit')) {
            $dataset = array_slice($dataset, 0, $limit);
        }

        $user = $this->resolveUser();
        if (!$user) {
            return self::FAILURE;
        }

        $this->info('Running '.count($dataset)." prompts as user '{$user->name}' (#{$user->id})");
        $this->info('Model:    '.config('assistant.openrouter.model'));
        $providerOrder = (array) config('assistant.provider.order', []);
        $this->info('Provider: '.(empty($providerOrder) ? '(auto)' : implode(', ', $providerOrder)));
        $this->newLine();

        $startTime = microtime(true);
        $results = [];

        $bar = $this->output->createProgressBar(count($dataset));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('starting');
        $bar->start();

        foreach ($dataset as $entry) {
            $bar->setMessage("{$entry['id']}");
            $results[] = $this->runOne($service, $user, $entry);
            $bar->advance();
        }

        $bar->setMessage('done');
        $bar->finish();
        $this->newLine(2);

        $elapsedSeconds = round(microtime(true) - $startTime, 1);

        // Write the markdown report
        $outputPath = $this->option('output')
            ?: base_path('tests/AssistantEval/results/'.now()->format('Y-m-d_His').'.md');

        $dir = dirname($outputPath);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($outputPath, $this->renderMarkdown($results, $elapsedSeconds));

        $errorCount = collect($results)->where('error', '!=', null)->count();
        $successCount = count($results) - $errorCount;

        $this->info("Wrote report: {$outputPath}");
        $this->info("Total: {$successCount} OK, {$errorCount} errors, {$elapsedSeconds}s elapsed.");

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $userId = $this->option('user');

        if ($userId) {
            $user = User::find((int) $userId);
            if (!$user) {
                $this->error("User #{$userId} not found.");

                return null;
            }

            return $user;
        }

        $user = User::whereHas('roles', fn ($q) => $q->where('slug', 'platform_admin'))->first();
        if (!$user) {
            $this->error('No platform_admin user found. Pass --user=ID to choose one.');

            return null;
        }

        return $user;
    }

    /**
     * Run a single prompt and capture all events.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function runOne(AssistantService $service, User $user, array $entry): array
    {
        $messages = [
            ['role' => 'user', 'content' => (string) $entry['prompt']],
        ];

        $events = [];
        $start = microtime(true);

        try {
            // Disable streaming so we can capture the full response cleanly
            config(['assistant.streaming' => false]);

            $content = $service->chat(
                $messages,
                $user,
                function (string $type, mixed $data) use (&$events): void {
                    $events[] = ['type' => $type, 'data' => $data];
                }
            );

            return [
                'id' => $entry['id'] ?? null,
                'category' => $entry['category'] ?? null,
                'prompt' => $entry['prompt'] ?? '',
                'checks' => $entry['checks'] ?? [],
                'content' => $content,
                'events' => $events,
                'elapsed_ms' => (int) round((microtime(true) - $start) * 1000),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'id' => $entry['id'] ?? null,
                'category' => $entry['category'] ?? null,
                'prompt' => $entry['prompt'] ?? '',
                'checks' => $entry['checks'] ?? [],
                'content' => null,
                'events' => $events,
                'elapsed_ms' => (int) round((microtime(true) - $start) * 1000),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function renderMarkdown(array $results, float $elapsedSeconds): string
    {
        $model = config('assistant.openrouter.model');
        $provider = (array) config('assistant.provider.order', []);
        $now = now()->format('Y-m-d H:i:s');
        $count = count($results);
        $errors = collect($results)->where('error', '!=', null)->count();
        $ok = $count - $errors;

        $providerLine = empty($provider) ? '(auto)' : implode(', ', $provider);

        // Aggregate token + cost totals across all runs
        $totalPromptTokens = 0;
        $totalCompletionTokens = 0;
        foreach ($results as $r) {
            $usage = collect($r['events'])->where('type', 'usage')->last();
            $totalPromptTokens += (int) ($usage['data']['prompt_tokens'] ?? 0);
            $totalCompletionTokens += (int) ($usage['data']['completion_tokens'] ?? 0);
        }
        $totalTokens = $totalPromptTokens + $totalCompletionTokens;
        $totalCost = $this->estimateCost($totalPromptTokens, $totalCompletionTokens);

        $tokenLine = $totalTokens > 0
            ? '- **Tokens:** '.number_format($totalPromptTokens).' in / '
                .number_format($totalCompletionTokens).' out ('
                .number_format($totalTokens).' total)'
                .($totalCost !== null ? " — **~\${$totalCost}**" : '')
            : '';

        $md = <<<MD
# Assistant evals — {$now}

- **Model:** `{$model}`
- **Provider order:** {$providerLine}
- **Prompts:** {$count} ({$ok} OK, {$errors} errors)
- **Total runtime:** {$elapsedSeconds}s
{$tokenLine}

> **How to use:** scroll through each prompt below, mark each ✅/❌ check, set a rating
> in the box, and add notes. Then share this file back so the prompt and tools can
> be tuned.

## Contents

MD;

        // Table of contents
        foreach ($results as $i => $r) {
            $n = $i + 1;
            $anchor = $this->slug((string) $r['id']);
            $md .= "{$n}. [{$r['id']}](#{$n}-{$anchor}) — `{$r['category']}`\n";
        }
        $md .= "\n---\n\n";

        // Each result
        foreach ($results as $i => $r) {
            $n = $i + 1;
            $md .= $this->renderEntry($n, $r);
        }

        return $md;
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function renderEntry(int $n, array $r): string
    {
        $id = $r['id'];
        $category = $r['category'];
        $prompt = $r['prompt'];
        $elapsed = number_format($r['elapsed_ms'] / 1000, 1).'s';

        // Token usage from the recorded events (last 'usage' event wins)
        $usage = collect($r['events'])->where('type', 'usage')->last();
        $promptTokens = (int) ($usage['data']['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($usage['data']['completion_tokens'] ?? 0);
        $totalTokens = $promptTokens + $completionTokens;

        $md = "## {$n}. {$id}\n\n";
        $md .= "**Category:** `{$category}` · **Time:** {$elapsed}";
        if ($totalTokens > 0) {
            $cost = $this->estimateCost($promptTokens, $completionTokens);
            $md .= " · **Tokens:** {$promptTokens} in / {$completionTokens} out";
            if ($cost !== null) {
                $md .= " · **~Cost:** \${$cost}";
            }
        }
        $md .= "\n\n";

        $md .= "### Prompt\n\n";
        $md .= "> {$this->blockquote($prompt)}\n\n";

        // Tool calls
        $toolCalls = $this->extractToolCalls($r['events']);
        $maxIter = (int) config('assistant.limits.max_tool_iterations', 4);
        $iterCapHit = count($toolCalls) >= $maxIter;

        if (!empty($toolCalls)) {
            $header = '### Tool calls ('.count($toolCalls).')';
            if ($iterCapHit) {
                $header .= ' ⚠️ iteration cap hit';
            }
            $md .= $header."\n\n";

            foreach ($toolCalls as $idx => $tc) {
                $md .= ($idx + 1).". `{$tc['name']}`";
                if ($tc['args'] !== null) {
                    $argSummary = $this->summariseArgs($tc['args']);
                    if ($argSummary !== '') {
                        $md .= " — `{$argSummary}`";
                    }
                }
                $md .= "\n";
            }
            $md .= "\n";

            // Highlight repeated tool calls — usually a signal of model confusion
            $repeats = $this->detectRepeats($toolCalls);
            if (!empty($repeats)) {
                $md .= '> ⚠️ **Repeated calls:** '.implode(', ', $repeats).' — model retried the same tool. '
                    ."If this isn't intentional, check the system prompt or tool result format.\n\n";
            }
        } else {
            $md .= "### Tool calls\n\n_None._\n\n";
        }

        // Cards/maps emitted
        $cardEvents = collect($r['events'])
            ->whereIn('type', ['cave_cards', 'hut_cards', 'trip_report_cards', 'collection_cards'])
            ->all();

        if (!empty($cardEvents)) {
            $md .= "### UI cards emitted\n\n";
            foreach ($cardEvents as $ev) {
                $md .= "- `{$ev['type']}` ";
                if ($ev['type'] === 'cave_cards' && is_array($ev['data'])) {
                    $names = collect($ev['data'])->pluck('name')->take(5)->implode(', ');
                    $md .= '('.count($ev['data'])."): {$names}";
                } elseif ($ev['type'] === 'hut_cards' && isset($ev['data']['huts'])) {
                    $names = collect($ev['data']['huts'])->pluck('name')->take(5)->implode(', ');
                    $md .= '('.count($ev['data']['huts'])."): {$names}";
                } elseif ($ev['type'] === 'trip_report_cards' && is_array($ev['data'])) {
                    $titles = collect($ev['data'])->pluck('title')->take(5)->implode(', ');
                    $md .= '('.count($ev['data'])."): {$titles}";
                } elseif ($ev['type'] === 'collection_cards' && is_array($ev['data'])) {
                    $names = collect($ev['data'])
                        ->map(fn ($c) => ($c['name'] ?? '').' ('.($c['user_progress'] ?? '').')')
                        ->take(5)
                        ->implode(', ');
                    $md .= '('.count($ev['data'])."): {$names}";
                }
                $md .= "\n";
            }
            $md .= "\n";
        }

        // Response
        if ($r['error']) {
            $md .= "### Response\n\n❌ **Error:** `{$r['error']}`\n\n";
        } else {
            $md .= "### Response\n\n";
            $md .= $this->indentForBlockquote((string) $r['content'])."\n\n";
        }

        // Checks for the user to tick
        if (!empty($r['checks'])) {
            $md .= "### Checks\n\n";
            foreach ($r['checks'] as $check) {
                $md .= "- [ ] {$check}\n";
            }
            $md .= "\n";
        }

        // Rating
        $md .= "### Rating\n\n";
        $md .= "- [ ] 🟢 Good (ship it)\n";
        $md .= "- [ ] 🟡 OK (works, has issues)\n";
        $md .= "- [ ] 🔴 Bad (broken or wrong)\n\n";
        $md .= "**Notes:**\n\n> _Your feedback here…_\n\n";
        $md .= "---\n\n";

        return $md;
    }

    /**
     * Pull tool-call records out of the recorded event stream.
     *
     * The service emits status=running with name + args, then status=done.
     *
     * @param  array<int, array{type: string, data: mixed}>  $events
     * @return array<int, array{name: string, args: array<string, mixed>|null}>
     */
    private function extractToolCalls(array $events): array
    {
        $calls = [];
        foreach ($events as $ev) {
            if (
                $ev['type'] === 'tool_call'
                && is_array($ev['data'])
                && ($ev['data']['status'] ?? null) === 'running'
            ) {
                $rawArgs = $ev['data']['args'] ?? null;
                $args = is_array($rawArgs) && !empty($rawArgs) ? $rawArgs : null;

                $calls[] = [
                    'name' => (string) ($ev['data']['name'] ?? ''),
                    'args' => $args,
                ];
            }
        }

        return $calls;
    }

    /**
     * Look for tool names invoked more than once in a single turn — usually a
     * symptom of the model failing to converge and retrying the same call.
     *
     * @param  array<int, array{name: string, args: array<string, mixed>|null}>  $toolCalls
     * @return array<int, string>  e.g. ["search_caves×3"]
     */
    private function detectRepeats(array $toolCalls): array
    {
        $counts = [];
        foreach ($toolCalls as $tc) {
            $name = $tc['name'];
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        $repeats = [];
        foreach ($counts as $name => $n) {
            if ($n > 1) {
                $repeats[] = "{$name}×{$n}";
            }
        }

        return $repeats;
    }

    /**
     * Summarise args for the markdown report. Keeps it short.
     *
     * @param  array<string, mixed>  $args
     */
    private function summariseArgs(array $args): string
    {
        $bits = [];
        foreach ($args as $k => $v) {
            if (is_array($v)) {
                $v = '['.implode(',', array_map(fn ($x) => (string) $x, $v)).']';
            } elseif (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            }
            $bits[] = "{$k}={$v}";
        }

        return implode(' ', $bits);
    }

    /**
     * Rough USD cost estimate. Returns null if we don't know the pricing for the
     * configured model — better to omit than mislead. Set a custom price via:
     *   ASSISTANT_PRICE_INPUT_USD_PER_MTOK
     *   ASSISTANT_PRICE_OUTPUT_USD_PER_MTOK
     * (USD per million tokens — matches OpenRouter's pricing convention.).
     */
    private function estimateCost(int $promptTokens, int $completionTokens): ?string
    {
        $envInput = env('ASSISTANT_PRICE_INPUT_USD_PER_MTOK');
        $envOutput = env('ASSISTANT_PRICE_OUTPUT_USD_PER_MTOK');

        if ($envInput !== null && $envOutput !== null) {
            $inputPrice = (float) $envInput;
            $outputPrice = (float) $envOutput;
        } else {
            // Fallback price book — keep this small and approximate. Source: OpenRouter
            // model pricing as of 2026-05. Override via env for current numbers.
            $prices = [
                'anthropic/claude-3-5-haiku' => [0.80, 4.00],
                'anthropic/claude-3-5-sonnet' => [3.00, 15.00],
                'anthropic/claude-haiku-4-5' => [1.00, 5.00],
                'anthropic/claude-sonnet-4-6' => [3.00, 15.00],
                'anthropic/claude-opus-4-7' => [15.00, 75.00],
                'openai/gpt-4o-mini' => [0.15, 0.60],
                'openai/gpt-4o' => [2.50, 10.00],
                'google/gemini-flash-1.5' => [0.075, 0.30],
                'meta-llama/llama-3.3-70b-instruct' => [0.40, 0.40],
                'moonshotai/kimi-k2.6' => [0.50, 2.00],
            ];

            $model = (string) config('assistant.openrouter.model');
            if (!isset($prices[$model])) {
                return null;
            }

            [$inputPrice, $outputPrice] = $prices[$model];
        }

        $cost = ($promptTokens * $inputPrice + $completionTokens * $outputPrice) / 1_000_000;

        return number_format($cost, 4);
    }

    private function blockquote(string $text): string
    {
        return str_replace("\n", "\n> ", trim($text));
    }

    private function indentForBlockquote(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '> _(empty)_';
        }

        return '> '.str_replace("\n", "\n> ", $text);
    }

    private function slug(string $input): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $input) ?: '');
    }
}
