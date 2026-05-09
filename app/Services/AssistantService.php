<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\Assistant\AssistantTool;
use App\Services\Assistant\Tools\FindNearbyHutsTool;
use App\Services\Assistant\Tools\GetCaveDetailsTool;
use App\Services\Assistant\Tools\GetCaveSystemActivityTool;
use App\Services\Assistant\Tools\GetUpcomingPermitsTool;
use App\Services\Assistant\Tools\GetUserExperienceTool;
use App\Services\Assistant\Tools\GetWeatherForecastTool;
use App\Services\Assistant\Tools\ListRoutesTool;
use App\Services\Assistant\Tools\SearchCavesTool;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssistantService
{
    /** @var AssistantTool[] */
    private array $tools;

    public function __construct(
        GetUserExperienceTool $userExperienceTool,
        SearchCavesTool $searchCavesTool,
        GetCaveDetailsTool $caveDetailsTool,
        GetWeatherForecastTool $weatherForecastTool,
        GetUpcomingPermitsTool $upcomingPermitsTool,
        ListRoutesTool $listRoutesTool,
        FindNearbyHutsTool $findNearbyHutsTool,
        GetCaveSystemActivityTool $caveSystemActivityTool,
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
        ];
    }

    /**
     * Run the agentic tool loop and return the final assistant message content.
     *
     * @param  array<int, array{role: string, content: string}>  $messages  Full conversation history from the client
     * @param  callable|null  $onEvent  fn(string $type, mixed $data): void — called for SSE progress events
     */
    public function chat(array $messages, User $user, ?callable $onEvent = null): string
    {
        $apiKey = config('assistant.openrouter.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenRouter API key is not configured.');
        }

        $systemMessage = [
            'role' => 'system',
            'content' => $this->buildSystemPrompt($user),
        ];

        // Cap history to avoid ballooning token costs
        $maxHistory = (int) config('assistant.limits.max_history_messages', 20);
        $cappedMessages = array_slice($messages, -$maxHistory);

        $context = array_merge([$systemMessage], $cappedMessages);
        $toolDefinitions = $this->getToolDefinitions();

        $maxIterations = (int) config('assistant.limits.max_tool_iterations', 5);
        $iterations = 0;
        $lastContent = '';

        /** @var string[] $toolsUsed Names of all tools dispatched during this turn */
        $toolsUsed = [];

        /** @var array<string, array<string, mixed>> $caveCardBuffer Indexed by slug; emitted at end if mentioned */
        $caveCardBuffer = [];

        /** @var array<string, mixed>|null $hutCardsBuffer Most recent hut search result */
        $hutCardsBuffer = null;

        /** @var array<int, array<string, mixed>> $tripReportBuffer Recent trip reports from any tool call */
        $tripReportBuffer = [];

        /** @var array{prompt_tokens: int, completion_tokens: int} */
        $totalUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0];

        if ($onEvent) {
            $onEvent('thinking', null);
        }

        $thinkingStart = microtime(true);

        $useStreaming = config('assistant.streaming', true) && $onEvent !== null;

        do {
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

                    if ($onEvent) {
                        $onEvent('tool_call', [
                            'name' => $name,
                            'status' => 'running',
                            'args' => $args,
                        ]);
                    }

                    $result = $this->dispatchTool($name, $args, $user);

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

                    // Safety injection: if a river gauge is High, add a mandatory warning context
                    if ($name === 'get_weather_forecast') {
                        $this->injectSafetyAlert($result, $context, $message['content'] ?? '');
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
        } while ($hasToolCalls && $iterations < $maxIterations);

        // If we exited the loop while still in tool-calling mode (iter cap reached
        // without a final assistant message), force one more call with tools disabled
        // so the model has to produce a textual answer. Otherwise the user gets
        // either an empty response or a generic "I was unable…" which is the worst UX.
        if ($hasToolCalls) {
            Log::warning('AssistantService: tool iteration cap hit, forcing a final text answer', [
                'iterations' => $iterations,
            ]);

            $context[] = [
                'role' => 'system',
                'content' => 'You have reached the tool-call limit for this turn. '
                    .'Stop calling tools. Using ONLY the data returned so far, write a '
                    .'final answer to the user. If the data is insufficient, say so '
                    .'clearly and suggest a more specific question they could ask. '
                    .'Do not call any more tools.',
            ];

            try {
                $finalResponse = $this->callOpenRouter($context, [], $apiKey);
                $finalChoice = $finalResponse['choices'][0] ?? [];
                $finalMessage = $finalChoice['message'] ?? [];
                $finalText = (string) ($finalMessage['content'] ?? '');

                if ($finalText !== '') {
                    $lastContent = $finalText;
                }

                $totalUsage['prompt_tokens'] += (int) ($finalResponse['usage']['prompt_tokens'] ?? 0);
                $totalUsage['completion_tokens'] += (int) ($finalResponse['usage']['completion_tokens'] ?? 0);

                if ($onEvent && $finalText !== '') {
                    $onEvent('content_chunk', ['text' => $finalText]);
                }
            } catch (\Throwable $e) {
                Log::error('AssistantService: forced final answer failed', ['error' => $e->getMessage()]);
            }
        }

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

        // Emit contextual follow-up suggestions based on what was discussed
        if ($onEvent && !empty($toolsUsed)) {
            $suggestions = $this->buildSuggestions($toolsUsed, $context);
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

        return $lastContent ?: 'I was unable to generate a response. Please try again.';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getToolDefinitions(): array
    {
        return array_values(
            array_map(fn ($tool) => $tool::definition(), $this->tools)
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
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function dispatchTool(string $name, array $arguments, User $user): array
    {
        if (!isset($this->tools[$name])) {
            Log::warning('AssistantService: unknown tool requested', ['tool' => $name]);

            return ['error' => "Unknown tool: {$name}"];
        }

        try {
            return $this->tools[$name]->handle($arguments, $user);
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

        $matches = [];
        foreach ($buffer as $slug => $sys) {
            $name = (string) ($sys['name'] ?? '');
            if (
                ($slug !== '' && str_contains($reply, $slug))
                || ($name !== '' && stripos($reply, $name) !== false)
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

    private function buildSystemPrompt(User $user): string
    {
        $user->loadMissing('clubs');
        $clubs = $user->clubs
            ->filter(fn ($c) => $c->pivot->status === 'approved')
            ->map(fn ($c) => $c->name)
            ->join(', ');

        $date = now()->format('l, j F Y');
        $month = (int) now()->format('n');

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
You are Vern, a knowledgeable caving assistant for the Subterra platform (subterra.world).
You help cavers in the UK and Ireland choose appropriate trips and plan caving weekends.

Current date: {$date}
User: {$user->name}
Clubs: {$clubs}

## Seasonal Conditions
{$seasonalContext}

## Your Guidelines

**Tool budget is small.** You may make at most 4 tool calls per turn — usually 2-3 is plenty.
Plan before calling. Do NOT call the same tool repeatedly hoping for a better result. If a search
returns 0 results or unhelpful results, accept that, tell the user the data isn't in Subterra, and
suggest they try a different region/tag/name. Searching again with variations is almost always
the wrong move.

**Know the user — but only when relevant.** Call get_user_experience before personalised trip
recommendations (e.g. "what should I try next"), but DO NOT call it for accommodation queries,
weather/condition checks, or factual questions about a specific named cave. Do not call it twice
in the same turn.

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
PROMPT;
    }
}
