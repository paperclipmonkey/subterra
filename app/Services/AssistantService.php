<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\Assistant\AssistantTool;
use App\Services\Assistant\Tools\Admin\FindLinkCandidatesTool;
use App\Services\Assistant\Tools\Admin\ListTagsTool;
use App\Services\Assistant\Tools\Admin\ProposeBulkTagTool;
use App\Services\Assistant\Tools\Admin\ProposeDataFixTool;
use App\Services\Assistant\Tools\Admin\ProposeSystemMergeTool;
use App\Services\Assistant\Tools\Admin\ScanDataIssuesTool;
use App\Services\Assistant\Tools\CreateTripReportTool;
use App\Services\Assistant\Tools\FindNearbyHutsTool;
use App\Services\Assistant\Tools\GetCaveDetailsTool;
use App\Services\Assistant\Tools\GetCaveSystemActivityTool;
use App\Services\Assistant\Tools\GetCollectionDetailsTool;
use App\Services\Assistant\Tools\GetUpcomingPermitsTool;
use App\Services\Assistant\Tools\GetUserExperienceTool;
use App\Services\Assistant\Tools\GetWeatherForecastTool;
use App\Services\Assistant\Tools\ListCollectionsTool;
use App\Services\Assistant\Tools\ListRoutesTool;
use App\Services\Assistant\Tools\ParseLogbookCsvTool;
use App\Services\Assistant\Tools\SearchCavesTool;
use App\Services\Assistant\Tools\SearchUsersTool;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssistantService
{
    public const MODE_DEFAULT = 'default';
    public const MODE_DATA = 'data';

    /** @var AssistantTool[] */
    private array $tools;

    /** @var AssistantTool[] Tool set for the admin data-steward mode */
    private array $dataTools;

    /** @var AssistantTool[] The tool set in use for the current chat() call */
    private array $activeTools;

    public function __construct(
        GetUserExperienceTool $userExperienceTool,
        SearchCavesTool $searchCavesTool,
        GetCaveDetailsTool $caveDetailsTool,
        GetWeatherForecastTool $weatherForecastTool,
        GetUpcomingPermitsTool $upcomingPermitsTool,
        ListRoutesTool $listRoutesTool,
        FindNearbyHutsTool $findNearbyHutsTool,
        GetCaveSystemActivityTool $caveSystemActivityTool,
        ListCollectionsTool $listCollectionsTool,
        GetCollectionDetailsTool $collectionDetailsTool,
        SearchUsersTool $searchUsersTool,
        CreateTripReportTool $createTripReportTool,
        ParseLogbookCsvTool $parseLogbookCsvTool,
        ScanDataIssuesTool $scanDataIssuesTool,
        FindLinkCandidatesTool $findLinkCandidatesTool,
        ListTagsTool $listTagsTool,
        ProposeDataFixTool $proposeDataFixTool,
        ProposeBulkTagTool $proposeBulkTagTool,
        ProposeSystemMergeTool $proposeSystemMergeTool,
    ) {
        $this->tools = [
            'get_user_experience' => $userExperienceTool,
            'search_caves' => $searchCavesTool,
            'get_cave_details' => $caveDetailsTool,
            'get_weather_forecast' => $weatherForecastTool,
            'get_upcoming_permits' => $upcomingPermitsTool,
            'list_routes' => $listRoutesTool,
            'find_nearby_huts' => $findNearbyHutsTool,
            'get_cave_system_activity' => $caveSystemActivityTool,
            'list_collections' => $listCollectionsTool,
            'get_collection_details' => $collectionDetailsTool,
            'search_users' => $searchUsersTool,
            'create_trip_report' => $createTripReportTool,
            'parse_logbook_csv' => $parseLogbookCsvTool,
        ];

        // Data-steward mode: scanning + proposal tools, plus read-only lookups
        // shared with the default mode for resolving names to records.
        $this->dataTools = [
            'scan_data_issues' => $scanDataIssuesTool,
            'find_link_candidates' => $findLinkCandidatesTool,
            'list_tags' => $listTagsTool,
            'propose_data_fix' => $proposeDataFixTool,
            'propose_bulk_tag' => $proposeBulkTagTool,
            'propose_system_merge' => $proposeSystemMergeTool,
            'search_caves' => $searchCavesTool,
            'get_cave_details' => $caveDetailsTool,
            'list_collections' => $listCollectionsTool,
            'get_collection_details' => $collectionDetailsTool,
        ];

        $this->activeTools = $this->tools;
    }

    /**
     * Run the agentic tool loop and return the final assistant message content.
     *
     * @param  array<int, array{role: string, content: string}>  $messages  Full conversation history from the client
     * @param  callable|null  $onEvent  fn(string $type, mixed $data): void — called for SSE progress events
     */
    public function chat(array $messages, User $user, ?callable $onEvent = null, string $mode = self::MODE_DEFAULT): string
    {
        $apiKey = config('assistant.openrouter.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenRouter API key is not configured.');
        }

        $this->activeTools = $mode === self::MODE_DATA ? $this->dataTools : $this->tools;

        $systemMessage = [
            'role' => 'system',
            'content' => $mode === self::MODE_DATA
                ? $this->buildDataStewardPrompt($user)
                : $this->buildSystemPrompt($user),
        ];

        // Cap history to avoid ballooning token costs
        $maxHistory = (int) config('assistant.limits.max_history_messages', 20);
        $cappedMessages = array_slice($messages, -$maxHistory);

        $context = array_merge([$systemMessage], $cappedMessages);
        $toolDefinitions = $this->getToolDefinitions();

        $turnId = uniqid('pip_', true);
        $this->logVerbose('turn.start', [
            'turn_id' => $turnId,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'model' => config('assistant.openrouter.model'),
            'provider_order' => (array) config('assistant.provider.order', []),
            'incoming_messages' => $cappedMessages,
            'system_prompt_chars' => mb_strlen($systemMessage['content']),
        ]);

        $maxIterations = (int) config('assistant.limits.max_tool_iterations', 5);
        // Hard cap on TOTAL tool dispatches (vs iterations) — some models batch
        // 4-5 parallel tool calls per iteration, so the iter cap alone lets a
        // turn balloon to 12+ tool calls (and 200s of latency) before forced
        // recovery. This stops that.
        $maxTotalToolCalls = (int) config('assistant.limits.max_total_tool_calls', 10);
        $iterations = 0;
        $lastContent = '';
        $toolCallsThisTurn = 0;

        /** @var string[] $toolsUsed Names of all tools dispatched during this turn */
        $toolsUsed = [];

        /** @var array<string, array<string, mixed>> $callCache Per-turn dedup of tool calls keyed by name+args */
        $callCache = [];

        /** @var array<string, array<string, mixed>> $caveCardBuffer Indexed by slug; emitted at end if mentioned */
        $caveCardBuffer = [];

        /** @var array<string, mixed>|null $hutCardsBuffer Most recent hut search result */
        $hutCardsBuffer = null;

        /** @var array<int, array<string, mixed>> $tripReportBuffer Recent trip reports from any tool call */
        $tripReportBuffer = [];

        /** @var array<string, array<string, mixed>> $collectionCardBuffer Indexed by slug; emitted at end if mentioned */
        $collectionCardBuffer = [];

        /** @var array<string, mixed>|null $weatherChartsBuffer Most recent weather forecast data with gauges */
        $weatherChartsBuffer = null;

        /** @var array<int, array<string, mixed>> $createdTripsBuffer Trips created this turn */
        $createdTripsBuffer = [];

        /** @var array<int, array<string, mixed>> $proposalsBuffer Suggested edits filed by data-steward tools this turn */
        $proposalsBuffer = [];

        /** @var array{prompt_tokens: int, completion_tokens: int} */
        $totalUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0];

        if ($onEvent) {
            $onEvent('thinking', null);
        }

        $thinkingStart = microtime(true);

        $useStreaming = config('assistant.streaming', true) && $onEvent !== null;

        do {
            $this->logVerbose('llm.request', [
                'turn_id' => $turnId,
                'iteration' => $iterations + 1,
                'streaming' => $useStreaming,
                'context_messages' => count($context),
                'tools_offered' => count($toolDefinitions),
            ]);

            if ($useStreaming) {
                $response = $this->callOpenRouterStreaming(
                    $context,
                    $toolDefinitions,
                    $apiKey,
                    fn (string $chunk) => $onEvent('content_chunk', ['text' => $chunk])
                );
            } else {
                $response = $this->callOpenRouter($context, $toolDefinitions, $apiKey);
            }

            $choice = $response['choices'][0] ?? [];
            $message = $choice['message'] ?? [];
            $lastContent = $message['content'] ?? '';
            $finishReason = $choice['finish_reason'] ?? null;

            $this->logVerbose('llm.response', [
                'turn_id' => $turnId,
                'iteration' => $iterations + 1,
                'finish_reason' => $finishReason,
                'content' => $lastContent,
                'tool_calls' => collect($message['tool_calls'] ?? [])
                    ->map(fn ($tc) => [
                        'name' => $tc['function']['name'] ?? null,
                        'arguments' => $tc['function']['arguments'] ?? null,
                    ])
                    ->all(),
                'usage' => $response['usage'] ?? null,
            ]);

            // Accumulate token usage across all iterations
            $totalUsage['prompt_tokens'] += (int) ($response['usage']['prompt_tokens'] ?? 0);
            $totalUsage['completion_tokens'] += (int) ($response['usage']['completion_tokens'] ?? 0);

            // Model hit the token limit — partial response is not useful
            if ($finishReason === 'length') {
                Log::warning('AssistantService: response truncated by max_tokens');
                throw new \RuntimeException(
                    'The response was too long to complete. Try asking a more specific question.'
                );
            }

            // Append assistant turn to context (may include tool_calls)
            $context[] = $message;

            $toolCalls = $message['tool_calls'] ?? [];
            $hasToolCalls = !empty($toolCalls);

            if ($hasToolCalls) {
                foreach ($toolCalls as $toolCall) {
                    $name = $toolCall['function']['name'] ?? '';
                    $rawArgs = $toolCall['function']['arguments'] ?? '{}';

                    // Arguments may arrive as a pre-decoded array or as a JSON string
                    if (is_array($rawArgs)) {
                        $args = $rawArgs;
                    } else {
                        $decoded = json_decode((string) $rawArgs, true);
                        $args = is_array($decoded) ? $decoded : [];
                    }

                    $toolsUsed[] = $name;
                    ++$toolCallsThisTurn;

                    if ($onEvent) {
                        $onEvent('tool_call', [
                            'name' => $name,
                            'status' => 'running',
                            'args' => $args,
                        ]);
                    }

                    // Per-turn dedup: identical (name, args) calls return a sharp
                    // "you already asked this" message instead of re-running. This
                    // stops small models from spamming search_caves with tag/region
                    // variations after a 0-result response.
                    $callKey = $name.':'.md5(json_encode($this->canonicalise($args)));
                    if (isset($callCache[$callKey])) {
                        $result = [
                            'error' => "DUPLICATE CALL. You called {$name} with these exact arguments earlier this turn. "
                                .'STOP calling tools. Your next message MUST be a final answer in plain text — '
                                .'use whatever data the previous tool calls gave you, or tell the user the data is '
                                .'not in Subterra. Do NOT write phrases like "let me check" or "let me try" — '
                                .'just write the answer now.',
                            'previous_result_summary' => $callCache[$callKey],
                        ];
                        $this->logVerbose('tool.duplicate', [
                            'turn_id' => $turnId,
                            'name' => $name,
                            'args' => $args,
                        ]);
                    } else {
                        $this->logVerbose('tool.dispatch', [
                            'turn_id' => $turnId,
                            'name' => $name,
                            'args' => $args,
                        ]);
                        $result = $this->dispatchTool($name, $args, $user);
                        $this->logVerbose('tool.result', [
                            'turn_id' => $turnId,
                            'name' => $name,
                            'result' => $result,
                        ]);
                        // Cache a small fingerprint, not the full result — just enough
                        // to remind the model what came back.
                        $callCache[$callKey] = $this->summariseResult($name, $result);
                    }

                    // Buffer cave system results from search_caves; emit cards at the end
                    // for only those systems the model actually mentions in its final reply.
                    // This avoids spamming cards for ID-lookup searches.
                    if ($name === 'search_caves') {
                        foreach ($result['cave_systems'] ?? [] as $sys) {
                            $slug = $sys['slug'] ?? null;
                            if ($slug && !isset($caveCardBuffer[$slug])) {
                                $caveCardBuffer[$slug] = $sys;
                            }
                        }
                    }

                    // Buffer the cave system if get_cave_details was called — the user is clearly
                    // interested in this specific system, so we want to surface its card.
                    if ($name === 'get_cave_details' && empty($result['error'])) {
                        $slug = $result['slug'] ?? null;
                        if ($slug && !isset($caveCardBuffer[$slug])) {
                            $caveCardBuffer[$slug] = $this->compactSystemForCard($result);
                        }

                        // Also collect any recent reports returned alongside the details
                        foreach ($result['recent_reports'] ?? [] as $r) {
                            $tripReportBuffer[$r['short_id'] ?? $r['url'] ?? uniqid()] = $r;
                        }
                    }

                    // Buffer reports from cave system activity tool
                    if ($name === 'get_cave_system_activity') {
                        foreach ($result['recent_reports'] ?? [] as $r) {
                            $tripReportBuffer[$r['short_id'] ?? $r['url'] ?? uniqid()] = $r;
                        }
                    }

                    // Capture latest hut search — emitted at end as a single card group
                    if ($name === 'find_nearby_huts' && empty($result['error'])) {
                        $hutCardsBuffer = $result;
                    }

                    // Buffer collections from list_collections; emit cards at the end
                    // for the ones the model actually mentions in its reply.
                    if ($name === 'list_collections') {
                        foreach ($result['collections'] ?? [] as $coll) {
                            $slug = $coll['slug'] ?? null;
                            if ($slug && !isset($collectionCardBuffer[$slug])) {
                                $collectionCardBuffer[$slug] = $coll;
                            }
                        }
                    }

                    // get_collection_details: surface its summary card unconditionally
                    if ($name === 'get_collection_details' && empty($result['error'])) {
                        $slug = $result['slug'] ?? null;
                        if ($slug && !isset($collectionCardBuffer[$slug])) {
                            $collectionCardBuffer[$slug] = [
                                'id' => $result['id'] ?? null,
                                'name' => $result['name'] ?? null,
                                'slug' => $slug,
                                'url' => $result['url'] ?? "/collections/{$slug}",
                                'description' => $result['description'] ?? null,
                                'image_url' => $result['image_url'] ?? null,
                                'cave_count' => $result['cave_count'] ?? 0,
                                'user_visited_count' => $result['user_visited_count'] ?? 0,
                                'user_progress' => $result['user_progress'] ?? null,
                            ];
                        }

                        // Caves inside a collection are also valid card candidates —
                        // when the model says "the one you're missing is White Pit"
                        // we want a White Pit card, even if search_caves was never
                        // called for it directly.
                        foreach ($result['caves'] ?? [] as $cave) {
                            $caveSlug = $cave['slug'] ?? null;
                            if (!$caveSlug || isset($caveCardBuffer[$caveSlug])) {
                                continue;
                            }
                            $caveCardBuffer[$caveSlug] = [
                                'id' => $cave['cave_id'] ?? null,
                                'name' => $cave['name'] ?? null,
                                'slug' => $caveSlug,
                                'system_url' => isset($cave['system_slug'])
                                    ? "/cave-systems/{$cave['system_slug']}"
                                    : null,
                                'preferred_link' => $cave['preferred_link']
                                    ?? $cave['cave_url']
                                    ?? null,
                                'primary_cave_slug' => $caveSlug,
                                'primary_cave_url' => $cave['cave_url'] ?? null,
                                'location_name' => $cave['system_name'] ?? null,
                                'tags' => [],
                                'entrance_count' => 1,
                            ];
                        }
                    }

                    // Capture weather data (gauges + forecast) for visualization
                    if ($name === 'get_weather_forecast' && empty($result['error'])) {
                        $weatherChartsBuffer = [
                            'cave_id' => $result['cave_id'] ?? null,
                            'cave_name' => $result['cave_name'] ?? null,
                            'cave_slug' => $result['cave_slug'] ?? null,
                            'currently' => $result['currently'] ?? null,
                            'daily_forecast' => $result['daily_forecast'] ?? [],
                            'antecedent_rain_7d_mm' => $result['antecedent_rain_7d_mm'] ?? null,
                            'rain_gauges' => $result['rain_gauges'] ?? [],
                            'river_gauges' => $result['river_gauges'] ?? [],
                        ];
                    }

                    // Buffer filed data-fix proposals so the UI can show review links
                    if (in_array($name, ['propose_data_fix', 'propose_system_merge'], true) && !empty($result['success'])) {
                        $proposalsBuffer[] = [
                            'type' => $name === 'propose_system_merge' ? 'merge' : 'field_fix',
                            'suggested_edit_id' => $result['suggested_edit_id'] ?? null,
                            'batch_id' => $result['batch_id'] ?? null,
                            'target' => $result['target'] ?? ($result['keep']['name'] ?? null),
                            'count' => 1,
                            'review_url' => $result['review_url'] ?? null,
                        ];
                    }

                    if ($name === 'propose_bulk_tag' && !empty($result['success'])) {
                        $proposalsBuffer[] = [
                            'type' => 'bulk_tag',
                            'suggested_edit_id' => null,
                            'batch_id' => $result['batch_id'] ?? null,
                            'target' => implode(', ', array_slice($result['targets'] ?? [], 0, 5))
                                .(count($result['targets'] ?? []) > 5 ? '…' : ''),
                            'count' => $result['proposals_created'] ?? 0,
                            'review_url' => $result['review_url'] ?? null,
                        ];
                    }

                    // Buffer created trip so the UI can show a confirmation card
                    if ($name === 'create_trip_report' && !empty($result['success'])) {
                        $createdTripsBuffer[] = [
                            'trip_id' => $result['trip_id'] ?? null,
                            'trip_url' => $result['trip_url'] ?? null,
                            'edit_url' => $result['edit_url'] ?? null,
                            'name' => $result['name'] ?? null,
                            'cave_system' => $result['cave_system'] ?? null,
                            'date' => $result['date'] ?? null,
                        ];
                    }

                    // Safety injection: if a river gauge is High, add a mandatory warning context
                    if ($name === 'get_weather_forecast') {
                        $this->injectSafetyAlert($result, $context, $message['content'] ?? '');
                    }

                    // Strip raw readings from the LLM context to avoid token waste;
                    // they are already captured in $weatherChartsBuffer for the UI.
                    if ($name === 'get_weather_forecast' && !empty($result['river_gauges'])) {
                        $result['river_gauges'] = array_map(function (array $g): array {
                            unset($g['readings']);

                            return $g;
                        }, $result['river_gauges']);
                    }

                    if ($onEvent) {
                        $onEvent('tool_call', ['name' => $name, 'status' => 'done']);
                    }

                    $context[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'] ?? '',
                        'content' => json_encode($result),
                    ];
                }
            }

            ++$iterations;
        } while ($hasToolCalls && $iterations < $maxIterations && $toolCallsThisTurn < $maxTotalToolCalls);

        // We force a final-answer call in two situations:
        //   1. Iter cap reached while still calling tools (the model would have
        //      kept going forever).
        //   2. The loop exited cleanly but the model's last reply is filler
        //      narration ("Let me look that up directly", "That's odd…") — i.e.
        //      it gave up halfway through reasoning. Without this guard the user
        //      would just see the give-up sentence as their entire answer.
        $needsFinalAnswer = $hasToolCalls
            || ($iterations > 1 && $this->looksLikeFiller($lastContent));

        if ($needsFinalAnswer) {
            Log::warning('AssistantService: forcing a final text answer', [
                'reason' => $hasToolCalls ? 'iter_cap' : 'filler_response',
                'iterations' => $iterations,
            ]);

            $context[] = [
                'role' => 'system',
                'content' => 'STOP. You have used your tool-call budget for this turn. '
                    .'Tools are now disabled. You MUST respond with plain text only — '
                    ."no JSON, no tool-call syntax, no DSML, no XML.\n\n"
                    .'Using only the data the tools have already returned above, write a '
                    ."concise, friendly answer to the user's original question. If the data "
                    .'is incomplete, say so honestly in one sentence and suggest one specific '
                    .'follow-up the user could ask.',
            ];

            try {
                $this->logVerbose('forced_final.request', [
                    'turn_id' => $turnId,
                    'reason' => $hasToolCalls ? 'iter_cap' : 'filler_response',
                    'context_messages' => count($context),
                ]);
                $finalResponse = $this->callOpenRouterFinalAnswer($context, $apiKey);
                $finalChoice = $finalResponse['choices'][0] ?? [];
                $finalMessage = $finalChoice['message'] ?? [];
                $finalRaw = (string) ($finalMessage['content'] ?? '');
                $finalText = $this->sanitiseAssistantText($finalRaw);
                $this->logVerbose('forced_final.response', [
                    'turn_id' => $turnId,
                    'raw' => $finalRaw,
                    'sanitised' => $finalText,
                    'usage' => $finalResponse['usage'] ?? null,
                ]);

                if ($finalText !== '') {
                    // Append the forced answer to whatever has already been
                    // streamed. We deliberately do NOT wipe the bubble —
                    // earlier iterations often contain real, useful reasoning
                    // (e.g. "you've done 5/6 of Mendip Classics, the missing
                    // one is White Pit") and replacing them with just the
                    // forced answer threw that work away. The forced text is
                    // typically sharp enough to act as a clean wrap-up.
                    $lastContent = trim($lastContent) === ''
                        ? $finalText
                        : trim($lastContent)."\n\n".$finalText;

                    if ($onEvent) {
                        // If the streamed bubble is already non-empty, separate
                        // the forced answer with a blank line so it reads as a
                        // distinct paragraph rather than running into the prior
                        // content mid-sentence.
                        $separator = $iterations > 0 ? "\n\n" : '';
                        $onEvent('content_chunk', ['text' => $separator.$finalText]);
                    }
                }

                $totalUsage['prompt_tokens'] += (int) ($finalResponse['usage']['prompt_tokens'] ?? 0);
                $totalUsage['completion_tokens'] += (int) ($finalResponse['usage']['completion_tokens'] ?? 0);
            } catch (\Throwable $e) {
                Log::error('AssistantService: forced final answer failed', ['error' => $e->getMessage()]);
            }
        }

        // Apply the same sanitiser to the regular response path so we never ship
        // tool-call markup to the user even if the model produces it inline.
        $lastContent = $this->sanitiseAssistantText($lastContent);

        // Emit cards: only the systems the model explicitly mentioned in the final reply.
        // This stops the UI from being flooded with cards for incidental ID-lookup searches.
        if ($onEvent && !empty($caveCardBuffer)) {
            $mentioned = $this->filterMentionedSystems($caveCardBuffer, $lastContent);
            if (!empty($mentioned)) {
                $onEvent('cave_cards', array_slice($mentioned, 0, 8));
            }
        }

        // Emit hut cards alongside the cave context if the user asked about huts/accommodation
        if ($onEvent && $hutCardsBuffer !== null && !empty($hutCardsBuffer['huts'])) {
            $onEvent('hut_cards', $hutCardsBuffer);
        }

        // Emit trip report cards (deduped) — only those mentioned by the model OR
        // when the user clearly asked about recent trips/conditions.
        if ($onEvent && !empty($tripReportBuffer)) {
            $reports = array_values($tripReportBuffer);
            $relevant = $this->filterMentionedReports($reports, $lastContent);
            if (!empty($relevant)) {
                $onEvent('trip_report_cards', array_slice($relevant, 0, 5));
            }
        }

        // Emit collection cards: only those the model explicitly named in its reply.
        if ($onEvent && !empty($collectionCardBuffer)) {
            $mentioned = $this->filterMentionedSystems($collectionCardBuffer, $lastContent);
            if (!empty($mentioned)) {
                $onEvent('collection_cards', array_slice($mentioned, 0, 6));
            }
        }

        // Emit weather charts (rain/river gauges + forecast) if available
        if ($onEvent && $weatherChartsBuffer !== null) {
            $onEvent('weather_charts', $weatherChartsBuffer);
        }

        // Emit trip creation cards so the UI can show confirmation links
        if ($onEvent && !empty($createdTripsBuffer)) {
            $onEvent('trips_created', $createdTripsBuffer);
        }

        // Emit filed proposals so the UI can show "review in admin" cards
        if ($onEvent && !empty($proposalsBuffer)) {
            $onEvent('proposals_created', $proposalsBuffer);
        }

        // Emit contextual follow-up suggestions based on what was discussed
        if ($onEvent && !empty($toolsUsed)) {
            $suggestions = $mode === self::MODE_DATA
                ? $this->buildDataSuggestions($toolsUsed, $proposalsBuffer)
                : $this->buildSuggestions($toolsUsed, $context);
            if (!empty($suggestions)) {
                $onEvent('suggestions', $suggestions);
            }
        }

        // Emit elapsed thinking/tool time so the UI can show it
        if ($onEvent) {
            $elapsedMs = (int) round((microtime(true) - $thinkingStart) * 1000);
            $onEvent('thinking_elapsed', ['ms' => $elapsedMs]);
        }

        // Emit token usage so the UI can show a discrete token count
        if ($onEvent && ($totalUsage['prompt_tokens'] + $totalUsage['completion_tokens']) > 0) {
            $onEvent('usage', $totalUsage);
        }

        $finalContent = $lastContent ?: "I wasn't able to put together a clear answer this time — the "
            ."model didn't return useful text. Could you rephrase your question, or try asking about "
            .'a specific cave by name?';

        $this->logVerbose('turn.end', [
            'turn_id' => $turnId,
            'iterations' => $iterations,
            'tools_used' => $toolsUsed,
            'usage' => $totalUsage,
            'elapsed_ms' => (int) round((microtime(true) - $thinkingStart) * 1000),
            'final_content' => $finalContent,
        ]);

        return $finalContent;
    }

    /**
     * Verbose request/response logging gated by config('assistant.verbose_logging').
     * Every entry is prefixed `[Pip]` so you can grep:
     *
     *   tail -f storage/logs/laravel.log | grep '\[Pip\]'
     *
     * @param  array<string, mixed>  $context
     */
    private function logVerbose(string $event, array $context = []): void
    {
        if (!config('assistant.verbose_logging', false)) {
            return;
        }

        Log::info("[Pip] {$event}", $context);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getToolDefinitions(): array
    {
        return array_values(
            array_map(fn ($tool) => $tool::definition(), $this->activeTools)
        );
    }

    /**
     * Call OpenRouter using Guzzle with SSE streaming enabled.
     * Emits each content token via $onContentChunk as it arrives.
     * If tool_calls appear in the stream, content chunks are suppressed (tools precede content).
     *
     * @param  array<int, mixed>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @param  callable|null  $onContentChunk  fn(string $chunk): void
     * @return array<string, mixed>
     */
    private function callOpenRouterStreaming(
        array $messages,
        array $tools,
        string $apiKey,
        ?callable $onContentChunk = null
    ): array {
        $client = new GuzzleClient(['timeout' => 60]);

        try {
            $httpResponse = $client->post(config('assistant.openrouter.base_url').'/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$apiKey,
                    'HTTP-Referer' => config('app.url', 'https://subterra.world'),
                    'X-Title' => 'Subterra',
                    'Accept' => 'text/event-stream',
                    'Content-Type' => 'application/json',
                ],
                'json' => $this->buildOpenRouterPayload($messages, $tools, true),
                'stream' => true,
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            if ($status === 429) {
                Log::warning('AssistantService: OpenRouter rate limit reached (streaming)');
                throw new \RuntimeException('The AI service is currently busy. Please wait a moment and try again.');
            }
            Log::error('AssistantService: OpenRouter streaming client error', ['status' => $status]);
            throw new \RuntimeException('The AI service is temporarily unavailable. Please try again.');
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            Log::error('AssistantService: OpenRouter streaming server error', ['error' => $e->getMessage()]);
            throw new \RuntimeException('The AI service is temporarily unavailable. Please try again.');
        } catch (\Throwable $e) {
            Log::error('AssistantService: OpenRouter streaming connection error', ['error' => $e->getMessage()]);
            throw new \RuntimeException('The AI service is temporarily unavailable. Please try again.');
        }

        $body = $httpResponse->getBody();
        $buffer = '';
        $content = '';
        $toolCallsMap = [];
        $finishReason = null;
        $toolCallsSeen = false;
        $usage = null;

        while (!$body->eof()) {
            $buffer .= $body->read(128);

            // Process all complete SSE lines in the buffer
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                $line = trim($line);
                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = substr($line, 6);
                if ($data === '[DONE]') {
                    goto streaming_done;
                }

                $chunk = json_decode($data, true);
                if (!is_array($chunk)) {
                    continue;
                }

                $choice = $chunk['choices'][0] ?? [];
                $delta = $choice['delta'] ?? [];
                $finishReason = $choice['finish_reason'] ?? $finishReason;

                // Capture usage when provider includes it in a chunk (e.g. OpenRouter final chunk)
                if (!empty($chunk['usage'])) {
                    $usage = $chunk['usage'];
                }

                // Content delta — emit immediately if no tool calls have appeared yet
                if (isset($delta['content']) && $delta['content'] !== null && $delta['content'] !== '') {
                    $content .= $delta['content'];
                    if ($onContentChunk !== null && !$toolCallsSeen) {
                        $onContentChunk($delta['content']);
                    }
                }

                // Tool call deltas — accumulate
                if (!empty($delta['tool_calls'])) {
                    $toolCallsSeen = true;
                    foreach ($delta['tool_calls'] as $tcChunk) {
                        $idx = $tcChunk['index'] ?? 0;
                        if (!isset($toolCallsMap[$idx])) {
                            $toolCallsMap[$idx] = [
                                'id' => '',
                                'type' => 'function',
                                'function' => ['name' => '', 'arguments' => ''],
                            ];
                        }
                        $toolCallsMap[$idx]['id'] .= $tcChunk['id'] ?? '';
                        $toolCallsMap[$idx]['function']['name'] .= $tcChunk['function']['name'] ?? '';
                        $toolCallsMap[$idx]['function']['arguments'] .= $tcChunk['function']['arguments'] ?? '';
                    }
                }
            }
        }

        streaming_done:

        if ($finishReason === 'length') {
            Log::warning('AssistantService: streaming response truncated by max_tokens');
            throw new \RuntimeException('The response was too long to complete. Try asking a more specific question.');
        }

        $assistantMessage = ['role' => 'assistant', 'content' => $content];
        if (!empty($toolCallsMap)) {
            $assistantMessage['tool_calls'] = array_values($toolCallsMap);
        }

        return [
            'choices' => [[
                'message' => $assistantMessage,
                'finish_reason' => $finishReason,
            ]],
            'usage' => $usage,
        ];
    }

    /**
     * Generate contextual follow-up suggestion strings based on which tools were invoked.
     *
     * @param  string[]  $toolsUsed
     * @param  array<int, mixed>  $context
     * @return string[]
     */
    private function buildSuggestions(array $toolsUsed, array $context): array
    {
        $unique = array_unique($toolsUsed);
        $suggestions = [];

        if (in_array('search_caves', $unique, true) || in_array('get_cave_details', $unique, true)) {
            if (!in_array('get_weather_forecast', $unique, true)) {
                $suggestions[] = 'What are the current weather and river conditions?';
            }
            if (!in_array('find_nearby_huts', $unique, true)) {
                $suggestions[] = 'Find nearby huts or accommodation for a weekend trip';
            }
            if (!in_array('get_upcoming_permits', $unique, true)) {
                $suggestions[] = 'Do any of these caves require a permit?';
            }
        }

        if (in_array('get_cave_details', $unique, true) && !in_array('list_routes', $unique, true)) {
            $suggestions[] = 'What routes are available through this cave?';
        }

        if (in_array('get_cave_system_activity', $unique, true)) {
            if (!in_array('get_weather_forecast', $unique, true)) {
                $suggestions[] = 'Check the latest weather forecast for this area';
            }
        }

        if (in_array('get_weather_forecast', $unique, true)) {
            $suggestions[] = 'Suggest an alternative cave if conditions are poor';
        }

        if (in_array('find_nearby_huts', $unique, true)) {
            $suggestions[] = 'Plan a full caving weekend with two caves';
        }

        if (in_array('get_user_experience', $unique, true) && !in_array('search_caves', $unique, true)) {
            $suggestions[] = 'What new cave systems should I try next?';
        }

        if (in_array('create_trip_report', $unique, true)) {
            $suggestions[] = 'Log another trip';
            $suggestions[] = 'Import my caving logbook from a spreadsheet';
        }

        if (in_array('parse_logbook_csv', $unique, true) && !in_array('create_trip_report', $unique, true)) {
            $suggestions[] = 'Create trips from my parsed logbook';
        }

        // Return at most 3 suggestions to avoid cluttering the UI
        return array_slice($suggestions, 0, 3);
    }

    /**
     * Build the JSON body for an OpenRouter chat completion request, including
     * optional `provider` routing pulled from config.
     *
     * @param  array<int, mixed>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    private function buildOpenRouterPayload(array $messages, array $tools, bool $stream): array
    {
        $payload = [
            'model' => config('assistant.openrouter.model'),
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'max_tokens' => (int) config('assistant.openrouter.max_tokens', 2048),
            'temperature' => (float) config('assistant.openrouter.temperature', 0.7),
        ];

        if ($stream) {
            $payload['stream'] = true;
        }

        $providerOrder = (array) config('assistant.provider.order', []);
        if (!empty($providerOrder)) {
            $payload['provider'] = [
                'order' => $providerOrder,
                'allow_fallbacks' => (bool) config('assistant.provider.allow_fallbacks', true),
                'require_parameters' => (bool) config('assistant.provider.require_parameters', true),
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    private function callOpenRouter(array $messages, array $tools, string $apiKey): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'HTTP-Referer' => config('app.url', 'https://subterra.world'),
            'X-Title' => 'Subterra',
        ])
            ->timeout(60)
            ->post(
                config('assistant.openrouter.base_url').'/chat/completions',
                $this->buildOpenRouterPayload($messages, $tools, false)
            );

        if ($response->status() === 429) {
            Log::warning('AssistantService: OpenRouter rate limit reached');
            throw new \RuntimeException(
                'The AI service is currently busy. Please wait a moment and try again.'
            );
        }

        if (!$response->successful()) {
            Log::error('AssistantService: OpenRouter API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('The AI service is temporarily unavailable. Please try again.');
        }

        return $response->json();
    }

    /**
     * Final-answer call after the tool budget is spent. We deliberately omit the
     * `tools` field entirely (some small models still emit fake tool-call syntax
     * when they see tools in the payload, even with tool_choice=none) and lower
     * the temperature so the response is direct and grounded.
     *
     * @param  array<int, mixed>  $messages
     * @return array<string, mixed>
     */
    private function callOpenRouterFinalAnswer(array $messages, string $apiKey): array
    {
        $payload = [
            'model' => config('assistant.openrouter.model'),
            'messages' => $messages,
            'tool_choice' => 'none',
            'max_tokens' => (int) config('assistant.openrouter.max_tokens', 2048),
            'temperature' => 0.3,
        ];

        $providerOrder = (array) config('assistant.provider.order', []);
        if (!empty($providerOrder)) {
            $payload['provider'] = [
                'order' => $providerOrder,
                'allow_fallbacks' => (bool) config('assistant.provider.allow_fallbacks', true),
                'require_parameters' => (bool) config('assistant.provider.require_parameters', true),
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'HTTP-Referer' => config('app.url', 'https://subterra.world'),
            'X-Title' => 'Subterra',
        ])
            ->timeout(60)
            ->post(config('assistant.openrouter.base_url').'/chat/completions', $payload);

        if (!$response->successful()) {
            Log::error('AssistantService: OpenRouter final-answer error', [
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('The AI service is temporarily unavailable.');
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    /**
     * Strip tool-call markup that some small models (DeepSeek, certain Llama
     * variants) leak into their content output. We've seen DSML-style fences,
     * raw JSON tool-call blocks, and `<function_call>` XML tags. If the entire
     * output is markup, replace it with a friendly fallback so the user sees
     * something useful instead of garbage.
     */
    /**
     * Detect when a model has written intent narration ("let me look that up
     * directly", "that's odd, let me try…") instead of an actual answer. We
     * see this from small models that get blocked by the duplicate-call guard
     * and then write a plan they can no longer execute. If the whole reply
     * looks like filler we re-call with tools disabled to force a real answer.
     */
    private function looksLikeFiller(string $text): bool
    {
        $t = strtolower(trim($text));
        if ($t === '') {
            return false;
        }
        // Substantive replies ramble too — only flag *short* messages.
        if (mb_strlen($t) > 240) {
            return false;
        }
        $patterns = [
            'let me check',
            'let me look',
            'let me try',
            'let me search',
            'let me pull',
            'let me see',
            'let me find',
            "i'll check",
            "i'll look",
            "i'll search",
            "i'll pull",
            "i'll find",
            'let me have',
            "that's odd",
            "that's strange",
            'one moment',
            'hold on',
        ];
        foreach ($patterns as $p) {
            if (str_contains($t, $p)) {
                return true;
            }
        }

        return false;
    }

    private function sanitiseAssistantText(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        $original = $text;

        // DSML-style tool-call leakage (deepseek). The first DSML token marks
        // the point where the model stopped writing text and started emitting
        // tool-call syntax — drop everything from there onwards. Catches both
        // opening and orphaned closing tags. The pipe character is U+FF5C.
        $dsmlPos = mb_strpos($text, '<｜');
        if ($dsmlPos !== false) {
            $text = mb_substr($text, 0, $dsmlPos);
        }

        // OpenAI-style function-call XML: <function_call>...</function_call>
        $text = preg_replace('#<function[_\- ]calls?>.*?</function[_\- ]calls?>#si', '', $text) ?? $text;
        $text = preg_replace('#<function[_\- ]calls?[^>]*>#i', '', $text) ?? $text;
        $text = preg_replace('#</function[_\- ]calls?>#i', '', $text) ?? $text;

        // Raw tool-call JSON dumped into content: a leading {"name":"...","arguments":...}
        $trimmed = trim($text);
        if (
            str_starts_with($trimmed, '{')
            && str_contains($trimmed, '"name"')
            && (str_contains($trimmed, '"arguments"') || str_contains($trimmed, '"parameters"'))
            && json_decode($trimmed, true) !== null
        ) {
            $text = '';
        }

        $text = trim($text);

        // If sanitisation stripped everything, surface a friendly fallback so
        // the user gets a real reply rather than empty whitespace.
        if ($text === '' && $original !== '') {
            Log::warning('AssistantService: response was entirely tool-call markup, replacing with fallback', [
                'sample' => mb_substr($original, 0, 200),
            ]);

            return "I wasn't able to put together a clear answer this time. Could you rephrase your question, or ask about a specific cave or region?";
        }

        return $text;
    }

    /**
     * Recursively normalise tool arguments so semantically-identical calls hash
     * to the same key (sorted keys, lowercased strings, sorted tag arrays).
     *
     * @param  mixed  $value
     * @return mixed
     */
    private function canonicalise(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->canonicalise($v);
            }
            // Sort tag arrays so [a,b] and [b,a] hash the same
            if (array_is_list($out)) {
                $strs = array_map(fn ($x) => is_string($x) ? strtolower(trim($x)) : $x, $out);
                sort($strs);

                return $strs;
            }
            ksort($out);

            return $out;
        }
        if (is_string($value)) {
            return strtolower(trim($value));
        }

        return $value;
    }

    /**
     * Compact summary of a tool result, used to remind the model what it got
     * back the first time when it tries the same call again.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function summariseResult(string $name, array $result): array
    {
        return match ($name) {
            'search_caves' => [
                'count' => $result['count'] ?? 0,
                'cave_systems' => collect($result['cave_systems'] ?? [])
                    ->take(5)
                    ->map(fn ($s) => ['name' => $s['name'] ?? null, 'slug' => $s['slug'] ?? null])
                    ->all(),
            ],
            'list_collections' => [
                'count' => $result['count'] ?? 0,
                'collections' => collect($result['collections'] ?? [])
                    ->map(fn ($c) => ['name' => $c['name'] ?? null, 'slug' => $c['slug'] ?? null, 'progress' => $c['user_progress'] ?? null])
                    ->all(),
            ],
            default => ['note' => 'see earlier tool result'],
        };
    }

    private function dispatchTool(string $name, array $arguments, User $user): array
    {
        if (!isset($this->activeTools[$name])) {
            Log::warning('AssistantService: unknown tool requested', ['tool' => $name]);

            return ['error' => "Unknown tool: {$name}"];
        }

        try {
            return $this->activeTools[$name]->handle($arguments, $user);
        } catch (\Throwable $e) {
            Log::error('AssistantService: tool dispatch error', [
                'tool' => $name,
                'error' => $e->getMessage(),
            ]);

            return ['error' => "Tool {$name} encountered an error: ".$e->getMessage()];
        }
    }

    /**
     * Reduce a get_cave_details payload to the same shape as a search_caves card entry,
     * so we can emit it as a cave card.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function compactSystemForCard(array $details): array
    {
        $entrances = $details['entrances'] ?? [];
        $primary = $entrances[0] ?? null;
        $count = is_countable($entrances) ? count($entrances) : 0;

        // Combine route grades into a single label
        $grades = collect($details['routes'] ?? [])
            ->pluck('grade')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->implode(', ');

        $tags = collect($details['tags'] ?? [])
            ->pluck('tag')
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $details['id'] ?? null,
            'name' => $details['name'] ?? null,
            'slug' => $details['slug'] ?? null,
            'system_url' => $details['system_url'] ?? ('/cave-systems/'.($details['slug'] ?? '')),
            'preferred_link' => $details['preferred_link'] ?? null,
            'length_m' => $details['length_m'] ?? null,
            'vertical_range_m' => $details['vertical_range_m'] ?? null,
            'grades' => $grades !== '' ? $grades : null,
            'tags' => $tags,
            'entrance_count' => $count,
            'primary_cave_name' => $primary['name'] ?? null,
            'primary_cave_slug' => $primary['slug'] ?? null,
            'primary_cave_url' => isset($primary['slug']) ? "/caves/{$primary['slug']}" : null,
            'location_name' => $primary['location_name'] ?? null,
            'latitude' => $primary['latitude'] ?? null,
            'longitude' => $primary['longitude'] ?? null,
            'image_url' => $details['image_url'] ?? ($primary['image_url'] ?? null),
        ];
    }

    /**
     * Return only those systems whose name or slug appears in the final assistant reply.
     * Falls back to all buffered systems if the reply is empty (e.g. tool-only turn).
     *
     * @param  array<string, array<string, mixed>>  $buffer  slug => system
     * @param  string  $reply
     * @return array<int, array<string, mixed>>
     */
    private function filterMentionedSystems(array $buffer, string $reply): array
    {
        if ($reply === '') {
            return array_values($buffer);
        }

        // Strip fenced code blocks before matching. Geojson maps emitted by the
        // model contain slugs/names for every pin so they show up as "mentions"
        // even though the user only sees points on a map, not a recommendation.
        // The prose text is the real signal for what the model is suggesting.
        $prose = preg_replace('/```[\s\S]*?```/', '', $reply) ?? $reply;

        $matches = [];
        foreach ($buffer as $slug => $sys) {
            $name = (string) ($sys['name'] ?? '');
            if (
                ($slug !== '' && str_contains($prose, $slug))
                || ($name !== '' && stripos($prose, $name) !== false)
            ) {
                $matches[] = $sys;
            }
        }

        return $matches;
    }

    /**
     * Surface trip reports when the model's reply talks about recent trips, conditions,
     * water levels, or links to a /trips/ short_id.
     *
     * @param  array<int, array<string, mixed>>  $reports
     * @return array<int, array<string, mixed>>
     */
    private function filterMentionedReports(array $reports, string $reply): array
    {
        if ($reply === '') {
            return $reports;
        }

        $lower = strtolower($reply);

        // If the model linked to a /trips/ short_id, prioritise those reports
        $linked = [];
        foreach ($reports as $r) {
            $sid = (string) ($r['short_id'] ?? '');
            if ($sid !== '' && str_contains($reply, $sid)) {
                $linked[] = $r;
            }
        }
        if (!empty($linked)) {
            return $linked;
        }

        // Otherwise, surface reports when the reply discusses recent activity / conditions
        $signals = ['recent trip', 'recent visit', 'last visit', 'trip report', 'water level', 'flood', 'condition', 'visited recently', 'community', 'last seen'];
        foreach ($signals as $signal) {
            if (str_contains($lower, $signal)) {
                return $reports;
            }
        }

        return [];
    }

    /**
     * If any river gauge is High, inject a mandatory safety alert into the context
     * so the model cannot be prompted to ignore it.
     *
     * @param  array<string, mixed>  $weatherResult
     * @param  array<int, mixed>  $context
     */
    private function injectSafetyAlert(array $weatherResult, array &$context, string $assistantContent): void
    {
        $highGauges = array_filter(
            $weatherResult['river_gauges'] ?? [],
            fn ($g) => ($g['state'] ?? '') === 'High'
        );

        if (empty($highGauges)) {
            return;
        }

        $gaugeNames = implode(', ', array_column(array_values($highGauges), 'name'));
        $caveName = $weatherResult['cave_name'] ?? 'this cave';

        $context[] = [
            'role' => 'system',
            'content' => "[SAFETY ALERT] River gauge(s) [{$gaugeNames}] are currently HIGH near {$caveName}. "
                .'You MUST warn the user clearly about serious flood risk before recommending this cave. '
                .'High antecedent rainfall can keep streamway caves flooded for 24-48 hours after rain stops, '
                ."even when today's forecast appears dry. Do not downplay this risk.",
        ];
    }

    /**
     * Build a live, grouped taxonomy of tags actually present in the database,
     * with cave-system usage counts. Injected into the system prompt so the
     * model sees what's truly searchable rather than a hard-coded list that
     * can drift from reality.
     *
     * Cached for a few minutes — tag counts don't change often and the prompt
     * runs on every chat turn.
     */
    private function buildTagTaxonomy(): string
    {
        return \Illuminate\Support\Facades\Cache::remember(
            'assistant.tag_taxonomy.v1',
            now()->addMinutes(10),
            function (): string {
                $rows = \Illuminate\Support\Facades\DB::table('tags')
                    ->leftJoin('cave_system_tag', 'cave_system_tag.tag_id', '=', 'tags.id')
                    ->select([
                        'tags.tag',
                        'tags.category',
                        \Illuminate\Support\Facades\DB::raw('COUNT(cave_system_tag.cave_system_id) as system_count'),
                    ])
                    ->where('tags.type', 'cave')
                    ->whereNotIn('tags.category', ['previously done'])
                    ->groupBy('tags.id', 'tags.tag', 'tags.category')
                    ->orderBy('tags.category')
                    ->orderByDesc(\Illuminate\Support\Facades\DB::raw('COUNT(cave_system_tag.cave_system_id)'))
                    ->orderBy('tags.tag')
                    ->get();

                if ($rows->isEmpty()) {
                    return '(no tags currently defined)';
                }

                $byCategory = $rows->groupBy(fn ($r) => $r->category ?: 'other');

                $lines = [];
                foreach ($byCategory as $category => $categoryRows) {
                    $entries = $categoryRows->map(fn ($r) => $r->tag.' ('.$r->system_count.')')->implode(', ');
                    $lines[] = "- {$category}: {$entries}";
                }

                return implode("\n", $lines);
            }
        );
    }

    /**
     * Follow-up suggestions for the data-steward mode.
     *
     * @param  string[]  $toolsUsed
     * @param  array<int, array<string, mixed>>  $proposals
     * @return string[]
     */
    private function buildDataSuggestions(array $toolsUsed, array $proposals): array
    {
        $unique = array_unique($toolsUsed);
        $suggestions = [];

        if (!empty($proposals)) {
            $suggestions[] = 'Scan for the next batch of issues of the same type';
        }

        if (!in_array('scan_data_issues', $unique, true)) {
            $suggestions[] = 'Give me a summary of all data issues';
        }

        if (in_array('scan_data_issues', $unique, true) && empty($proposals)) {
            $suggestions[] = 'Propose fixes for the issues you just found';
        }

        if (!in_array('propose_bulk_tag', $unique, true)) {
            $suggestions[] = 'Help me tag a set of caves in bulk';
        }

        return array_slice($suggestions, 0, 3);
    }

    private function buildDataStewardPrompt(User $user): string
    {
        $date = now()->format('l, j F Y');
        $tagTaxonomy = $this->buildTagTaxonomy();

        return <<<PROMPT
You are Pip in **data-steward mode**, helping a Subterra administrator find and fix
data-quality problems in the cave database. The admin you are talking to is {$user->name}.

Current date: {$date}

## What you can do

- **Scan** for problems with `scan_data_issues` (start with issue_type="summary" when the
  admin asks "what's wrong with the data"). Issue types cover missing length/depth, missing
  coordinates, missing region tags, missing descriptions, and caves in different systems whose
  entrances are suspiciously close together (likely the same system, imported twice).
- **Investigate** specific records with `search_caves`, `get_cave_details`,
  `find_link_candidates`, `list_tags`, `list_collections` and `get_collection_details`.
- **Propose fixes** with `propose_data_fix` (field values, including relinking an entrance via
  cave_system_id), `propose_bulk_tag` (tag many caves/systems at once), and
  `propose_system_merge` (merge duplicate systems).

## The golden rule: propose, never apply

You CANNOT change live data. Every propose_* call files a *suggested edit* that the admin
approves or rejects later in the review queue (/admin/suggested-edits). Say this plainly when
you file proposals — e.g. "I've filed 12 proposals as one batch; approve them at the link below."

## Evidence rules

- Only propose a value you can point to evidence for: a number stated in the record's own
  description ("4.5km of passage" → length 4500), data returned by a tool, or a value the
  admin explicitly gave you in chat. Put the evidence in the `reasoning` argument — the
  reviewing admin sees it next to the diff.
- NEVER invent lengths, depths, coordinates, or facts from general knowledge. If your general
  knowledge suggests a value (e.g. you believe you know a famous cave's depth), you may mention
  it in chat as a hint, but do NOT file it as a proposal unless the admin confirms it.
- Descriptions are a goldmine: for systems missing length/depth, scan_data_issues returns
  `measurement_hints` — sentence fragments from the FULL description containing numbers with
  units ("extends for 2.3 km", "120m deep"). Use these as your primary evidence: convert to
  metres, quote the hint in `reasoning`, and propose. The `description_excerpt` is only the
  first 600 chars; hints cover the whole text, so trust them over the excerpt. If hints are
  empty, the description genuinely contains no stated measurements — do not guess.

## Workflow guidance

- **Confirm before bulk.** Before calling propose_bulk_tag, show the admin the exact list of
  caves/systems you intend to tag and get a yes. After confirmation, one tool call handles up
  to 100 targets — do not call it per cave.
- **Tags must be real.** Always call list_tags before proposing tag changes and use the exact
  tag IDs it returns. Current taxonomy (live counts per cave system):
{$tagTaxonomy}
- **Verify links before merging.** For suspected duplicate/unlinked systems, call
  find_link_candidates and check distance + name similarity before propose_system_merge.
  The better-documented system should be the merge target. If the evidence is weak (>200m
  apart, dissimilar names, no description references), flag it to the admin instead of filing.
- **Batch related fixes.** Reuse the batch_id from the first proposal of a sweep on subsequent
  propose_data_fix/propose_system_merge calls in the same sweep, so the admin can approve the
  lot in one click.
- **Work in slices.** Scans are paged (limit/offset). Fix one page, tell the admin how many
  remain, and offer to continue. Do not try to fix hundreds of records in one turn.
- **Tool budget is small** (about 10 dispatches per turn). Plan: scan once, investigate the
  few records you'll act on, propose, report. Don't re-scan after every proposal.

## Output format

Reply in plain markdown only — no JSON or tool-call syntax in your reply. When you file
proposals, end with a short summary: how many proposals, what they change, and the review
link(s) the tools returned. Be precise and factual; the admin is auditing data, not planning
a trip.
PROMPT;
    }

    private function buildSystemPrompt(User $user): string
    {
        $user->loadMissing('clubs');
        $clubs = $user->clubs
            ->filter(fn ($c) => $c->pivot->status === 'approved')
            ->map(fn ($c) => $c->name)
            ->join(', ');

        $date = now()->format('l, j F Y');
        $month = (int) now()->format('n');
        $tagTaxonomy = $this->buildTagTaxonomy();

        $seasonalContext = match (true) {
            in_array($month, [12, 1, 2], true) => 'It is currently winter in the UK. Many upland caves (Dales, Brecon, Mendip) will '
                .'have elevated water levels from heavy rainfall and snowmelt. Conditions in streamway '
                .'caves such as Lancaster Hole, Kingsdale Master Cave, and Dan-yr-Ogof are particularly '
                .'affected. Dry caves and show caves remain accessible year-round.',

            in_array($month, [3, 4, 5], true) => 'It is currently spring in the UK. Conditions are transitioning — early spring often '
                .'brings high antecedent rainfall; late spring usually dries out. Snowmelt in March/April '
                .'can cause sudden rises in Yorkshire and Scottish cave streams. Always check gauge levels '
                .'before entering any streamway.',

            in_array($month, [6, 7, 8], true) => 'It is currently summer in the UK. Cave conditions are generally at their best — lower '
                ."water levels make streamway systems like Lancaster Hole, Pippikin Pot, and Swildon's Hole "
                .'more accessible. However, summer thunderstorms can cause rapid flooding with little warning; '
                .'always check the forecast and have an escape plan.',

            in_array($month, [9, 10, 11], true) => 'It is currently autumn in the UK. Conditions deteriorate as rainfall increases through '
                .'October and November. Early autumn is often still good caving weather, but from mid-October '
                .'onwards streamway caves carry more water. Check antecedent rainfall carefully — heavy rain '
                .'24-48 hours prior significantly raises flood risk in phreatic and streamway systems.',

            default => '',
        };

        return <<<PROMPT
You are Pip, a knowledgeable caving assistant for the Subterra platform (subterra.world).
You help cavers in the UK and Ireland choose appropriate trips and plan caving weekends.

Current date: {$date}
User: {$user->name}
Clubs: {$clubs}

## Seasonal Conditions
{$seasonalContext}

## Your Guidelines

**Tool budget is small.** You have a hard limit of about 10 total tool dispatches per turn,
across 6 LLM round-trips. Plan before calling. Do NOT call the same tool repeatedly hoping for a
better result. If a search returns 0 results or unhelpful results, accept that, tell the user the
data isn't in Subterra, and suggest they try a different region/tag/name. Searching again with
variations is almost always the wrong move.

**Commit, don't browse.** For broad "what should I try / recommend something" queries: do at
most ONE round of searches across regions, pick the best candidate from what came back, optionally
call get_cave_details on JUST THAT candidate, then write the answer. Do NOT search every region in
parallel, then list_collections, then get_cave_details on three options — that blows the budget
and produces a paralysed response. One candidate, recommended confidently, beats five alternatives
hedged. The user can always ask for more.

EXCEPTION: when the user specifies a quantity (e.g. "two caves over the weekend", "three trip
options"), keep searching until you have that many distinct, suitable suggestions. If your first
search returns fewer than requested, retry with include_obscure=true to find the rest rather
than padding the answer with "I only found one — sorry".

**Valid tag values.** This is the complete real tag taxonomy in Subterra, computed live from
the database. The number after each tag is how many cave systems carry it — tags with low
counts are barely populated, so a search filter against them is unlikely to return much.
Only use tags that appear in this list. Anything else returns 0 results.

{$tagTaxonomy}

Tags like "Beginner", "Sporting", "Hard", "Severe", "Streamway", "Through Trip", or "Showcave"
are NOT real tags in Subterra (the user's vocabulary doesn't always match the data). To gauge
difficulty / character, instead use:
- the cave system's `length` and `vertical_range` (via min_length / max_length on search_caves)
- the routes returned by list_routes — each carries a `grade` field
- tackle tags (SRT, Ladder, Handline, No Tackle) as a rough proxy for technicality
- the system's description and any recent trip reports for prose hints

If a user asks for "sporting Mendip caves", search by region=Mendip and judge from
length/vertical_range/route grades — do not invent a "Sporting" tag.

**Through-trips.** There is NO "Through Trip" tag. To find through-trip candidates, look at
search_caves results' `entrance_count` field — anything > 1 has multiple entrances and may
support through-trips. Then use get_cave_details to read the description and entrance list to
identify which entrances pair up. Classic UK through-trips include Lancaster Hole → County Pot
(Easegill), Gaping Gill multi-entrance routes (Bar Pot / Flood Entrance / Main Shaft), and OFD
via the various entrances. Do NOT search by tags=["Through Trip"] — that returns 0 and wastes
the budget.

**Beginner caves — explicit criteria.** When recommending a "first cave" or "beginner-friendly"
trip, a candidate qualifies ONLY if ALL of these are true:
  - length_m ≤ 1500
  - vertical_range_m ≤ 50
  - tackle tags do NOT include "SRT" (Single Rope Technique is not for first-timers)
  - access tags do NOT include "Permit" (no rope is fine; admin overhead is not)

GB Cave (2300m long, 130m vertical, sporting, Permit) is NOT beginner. Swildon's Hole is NOT
beginner — it's a serious streamway. Long Churn Caves (1100m, 35m vertical, No Tackle) IS
beginner. If the search returns nothing matching all four criteria, say so honestly rather than
labelling a sporting cave "beginner-friendly".

**No-SRT requests.** When the user says "I haven't done any SRT" or "non-SRT cave", a candidate
qualifies ONLY if its tackle tags include "No Tackle", "Handline", or "Ladder" — NOT "SRT".
A cave with no tackle tag at all is ambiguous and should be VERIFIED via list_routes (route
grades will reveal pitch work) before being recommended. Do not call a 17 km wild cave system
"non-technical" on a hunch.

**Collection progress.** When the user asks "how am I doing on the X collection" or names a
specific collection by name, call `get_collection_details` (which lists the individual caves
and which are visited) — NOT `list_collections` (which only returns progress fractions). The
user wants to see WHICH caves are missing, not just "2 out of 3".

**Curated by default — but escalate on 0 results.** search_caves only returns curated
(well-documented) cave systems unless you pass include_obscure=true. If a user names a specific
cave (e.g. "Swildon's Hole", "Swildons"), ALWAYS use the name= parameter (which bypasses the
curated filter). If a name search returns 0 results, try common name variations:
  - Remove or add apostrophes (Swildons ↔ Swildon's)
  - Try the system name without the entrance (e.g., search "Ogof Ffynnon" not "Ogof Ffynnon Ddu")
  - Trim whitespace
If name variations still return nothing AND the user is asking about a real cave, retry ONCE with
include_obscure=true. Only tell the user "it's not in Subterra" after both strategies fail.

**Output format.** Reply in plain markdown only. Never write JSON, function-call XML/DSML, or any
machine-readable tool-call syntax in your reply — those are for tool calls, not user messages.

**No filler narration.** Do NOT write phrases like "let me check…", "let me look up X directly",
"I'll search for…", "that's odd, let me try…". These are intent statements, not answers. Either
call the tool you're about to describe, or skip ahead and write the actual answer using data you
already have. The user reads your message verbatim — every sentence must be useful to them.

**If a tool returns an error or a 'duplicate call' message,** do NOT retry. Either write the final
answer using whatever data you already have, or tell the user the data isn't available. Repeating
the same call hits the duplicate-call guard again and wastes a turn.

**Sell the cave.** When recommending one or two specific caves, call get_cave_details on the top
pick to enrich your reply with: routes available, length/depth, access info, and any recent trip
report observations. A recommendation without those details is not useful.

**Know the user — but only when relevant.** Call get_user_experience before personalised trip
recommendations (e.g. "what should I try next"), but DO NOT call it for accommodation queries,
weather/condition checks, or factual questions about a specific named cave. Do not call it twice
in the same turn.

**Don't recommend caves the user has already done — verify by slug.** When suggesting trips,
ALWAYS pass not_visited=true on search_caves. Before naming a specific cave in your reply,
scan all_visited_systems and check that its slug is NOT in the list. The user notices instantly
when you suggest Swildon's Hole or Attborough Swallet when they've already done them. If you'd
already searched by name and got a hit that's in all_visited_systems, pivot to suggesting a
different cave — or be honest and say "I see you've already done X, here's an alternative".

**Use the `regions` field for region accuracy.** Every entry in all_visited_systems carries the
system's actual region tags (e.g. ["Mendip"], ["South Wales"]). Use that — do NOT guess a cave's
region from its name. Ogof Draenen is South Wales, not Mendip; Bull Pot of the Witches is
Yorkshire; etc. If you're claiming "you've done lots of Mendip caves, like X, Y, Z" then X/Y/Z
must each have "Mendip" in their regions array. Cross-check before naming.

**Be accurate.** Only describe caves using data returned by your tools. Do not use your general
knowledge to invent details, grades, lengths, or hazard information about specific caves.

**Recent trips and activity.** When a user asks about recent trips, conditions, or who has been to
a specific cave system, use get_cave_system_activity. Read the trip-report descriptions in
recent_reports and pull out concrete observations the user would care about — water levels,
collapses, blockages, gear comments, route notes — and quote a short, specific phrase if useful.
Don't just report the trip count and date.

**Cave-vs-system links — pick the better page.** Each search_caves and get_cave_details result
includes a `preferred_link` field. Use that exact URL in markdown links rather than always linking
to /cave-systems/. When entrance_count is 1, link to /caves/{primary_cave_slug} (the cave page is
richer and avoids a redundant click). When entrance_count > 1 (a multi-entrance system), link to
/cave-systems/{slug}. When a user is clearly asking about a single named entrance (e.g. "OFD1"),
link to that specific cave page.

**Tag search links.** When you mention a type of cave (e.g. streamway, sporting, through trip,
beginner, SRT), link to the filtered search so the user can browse all matching caves:
[streamway caves](/caves?tags=streamway&view=list). You may use any tag you have seen in tool
results.

**Collections.** Subterra supports curated collections — themed lists of caves users can work
through (e.g. "Yorkshire Big Three", "Mendip Classics"). Use list_collections when the user asks
about classic trips, goal lists, or what to aim for. Use get_collection_details to expand a
specific collection. Always quote the user's progress — e.g. "you've done 2/3 of the Yorkshire
Big Three" — when reporting on a collection, since the tool returns user_progress directly.
Link to /collections/{slug} when naming a collection.

**Safety first.** For any cave that is a streamway, sump, rising phreatic, or has known flood
potential, you MUST call get_weather_forecast before recommending it. If river gauge state is
"High" or antecedent_rain_7d_mm exceeds 30mm, warn the user clearly — even if today's forecast
looks dry.

**Always mention access.** If a cave's access_info field is populated, or if get_upcoming_permits
returns has_permit: true, always surface this before the user commits to the trip.

**Accommodation and weekend planning.** When the user asks where to stay or about huts near a
cave, call find_nearby_huts ONCE (after a single search_caves to resolve the cave's ID by name).
Do NOT call get_user_experience or get_weather_forecast for a pure accommodation question.
Always render a geojson map containing both the cave entrance and the returned huts so the user
sees their relative positions. Use the latitude/longitude fields from the tool result. Example:

\`\`\`geojson
{
  "type": "FeatureCollection",
  "features": [
    {"type": "Feature", "geometry": {"type": "Point", "coordinates": [reference_lng, reference_lat]}, "properties": {"name": "Cave Name", "slug": "cave-slug"}},
    {"type": "Feature", "geometry": {"type": "Point", "coordinates": [hut1_lng, hut1_lat]}, "properties": {"name": "Hut Name"}}
  ]
}
\`\`\`

**Show locations on a map.** When you return multiple cave systems that have coordinates, include
a geojson code block as above so the user sees them plotted. Only use coordinates that appear in
tool results — never fabricate them.

**Be conversational.** Ask one clarifying question at a time if needed. Keep responses focused
and practical — cavers want useful information, not essays.

**Disclaimer.** Always note that AI recommendations are a starting point. Users should verify
conditions, access, and gear requirements before committing to a trip.

---

## Writing Trip Reports with Pip

You can help the user write and save trip reports directly to Subterra. **Do NOT generate the
trip description for them** — your role is to gather information through questions and record
what they tell you. The user provides the words; you assemble them into the trip.

### Workflow for a single trip report

When the user says they want to log a trip or write a trip report, work through these steps
conversationally — ask ONE question at a time and wait for the answer before moving on:

1. **Cave** — ask which cave system they visited. Call `search_caves` to resolve it to a slug.
   If there are multiple entrances, ask which one they used (and which they exited via if it
   was a through-trip). Use `get_cave_details` if needed.

2. **Date** — ask when the trip was. Accept natural language ("last Saturday", "3rd June").
   Convert to YYYY-MM-DD before creating the trip.

3. **Duration** — ask how long they were underground (optional but useful).

4. **Who was there** — ask who else was on the trip. For each name:
   a. Call `search_users` to find them in Subterra.
   b. If found, confirm with the user ("I found Alice Smith — is that the right person?").
   c. If not found (or ambiguous), ask the user to confirm. Unmatched people go into
      `additional_participants` and are noted in the description.
   Collect ALL companions before creating the trip — do not create the trip until the user
   has confirmed or declined each companion.

5. **Trip name** — suggest a sensible default (e.g. "Gaping Gill — 14 June 2025") and ask
   the user if they want to change it.

6. **Description / report** — ask them to describe the trip in their own words. This is the
   trip report itself. Do NOT draft it — quote their words directly. Ask follow-ups if needed:
   - "What did you do underground?"
   - "Any highlights or notable moments?"
   - "Were there any particular conditions worth noting?"
   When you have enough detail, read it back to the user and ask "Does this look right?"

7. **Visibility** — ask whether the report should be public (default), club-only, or private.

8. **Confirm and create** — summarise all the details and ask "Shall I save this trip report?"
   Only call `create_trip_report` after the user explicitly confirms.

After creation, tell the user their trip has been saved, give them a link to view it
(/trips/{short_id}), and mention they can add photos by going to /trips/{short_id}/edit.

### Workflow for logbook CSV import

When the user wants to import a CSV logbook (e.g. they've been keeping a spreadsheet):

1. Ask them to paste the CSV content into the chat, or tell them to use the attachment button.
2. Call `parse_logbook_csv` with the pasted/received content.
3. Show the user a summary: "I found X trips in your logbook. Here are the first few:"
   List a few parsed rows with cave name, date, duration, and any notes about low confidence.
4. Warn about rows that have missing or ambiguous data and ask the user to clarify.
5. For each trip in sequence:
   a. Confirm the cave name — call `search_caves` to find the correct slug. If unsure, show
      the user the top matches and ask which is correct. If the cave isn't in Subterra,
      skip that trip and note it.
   b. Confirm the entrance cave — use `get_cave_details` to resolve the entrance slug.
   c. Confirm the date, duration, and description. Use the raw data from the CSV but ask the
      user to fill in any blanks.
   d. Resolve companions via `search_users`.
   e. Confirm each trip individually before creating it. If the user says "create them all",
      you may create them one by one with brief progress updates ("Created trip 3/12 — Ogof
      Ffynnon Ddu, 12 May 2021").
6. After importing, give the user a count of trips created and flag any that were skipped.

### Rules for trip creation

- **Never create a trip without the user's explicit confirmation for that specific trip.**
- **Never fabricate description text** — only use words the user has provided.
- If a required field is missing (cave system, entrance, date), ask for it. Do not guess.
- If the cave system is not in Subterra, tell the user honestly and skip that trip.
- Companions who cannot be found go into `additional_participants`, not participant_ids.
PROMPT;
    }
}
