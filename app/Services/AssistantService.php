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
            'get_user_experience'       => $userExperienceTool,
            'search_caves'              => $searchCavesTool,
            'get_cave_details'          => $caveDetailsTool,
            'get_weather_forecast'      => $weatherForecastTool,
            'get_upcoming_permits'      => $upcomingPermitsTool,
            'list_routes'               => $listRoutesTool,
            'find_nearby_huts'          => $findNearbyHutsTool,
            'get_cave_system_activity'  => $caveSystemActivityTool,
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
            'role'    => 'system',
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

            $choice       = $response['choices'][0] ?? [];
            $message      = $choice['message'] ?? [];
            $lastContent  = $message['content'] ?? '';
            $finishReason = $choice['finish_reason'] ?? null;

            // Accumulate token usage across all iterations
            $totalUsage['prompt_tokens']     += (int) ($response['usage']['prompt_tokens'] ?? 0);
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

            $toolCalls    = $message['tool_calls'] ?? [];
            $hasToolCalls = !empty($toolCalls);

            if ($hasToolCalls) {
                foreach ($toolCalls as $toolCall) {
                    $name    = $toolCall['function']['name'] ?? '';
                    $rawArgs = $toolCall['function']['arguments'] ?? '{}';

                    // Arguments may arrive as a pre-decoded array or as a JSON string
                    if (is_array($rawArgs)) {
                        $args = $rawArgs;
                    } else {
                        $decoded = json_decode((string) $rawArgs, true);
                        $args    = is_array($decoded) ? $decoded : [];
                    }

                    $toolsUsed[] = $name;

                    if ($onEvent) {
                        $onEvent('tool_call', ['name' => $name, 'status' => 'running']);
                    }

                    $result = $this->dispatchTool($name, $args, $user);

                    // Emit rich cave cards only for genuine recommendation results (3+ systems).
                    // Single/dual results are likely ID lookups used as stepping stones to another tool.
                    if ($name === 'search_caves' && $onEvent) {
                        $systems = $result['cave_systems'] ?? [];
                        if (count($systems) >= 3) {
                            $onEvent('cave_cards', array_slice($systems, 0, 8));
                        }
                    }

                    // Emit trip report cards when activity tool returns reports
                    if ($name === 'get_cave_system_activity' && $onEvent) {
                        $reports = $result['recent_reports'] ?? [];
                        if (count($reports) > 0) {
                            $onEvent('trip_report_cards', $reports);
                        }
                    }

                    // Safety injection: if a river gauge is High, add a mandatory warning context
                    if ($name === 'get_weather_forecast') {
                        $this->injectSafetyAlert($result, $context, $message['content'] ?? '');
                    }

                    if ($onEvent) {
                        $onEvent('tool_call', ['name' => $name, 'status' => 'done']);
                    }

                    $context[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $toolCall['id'] ?? '',
                        'content'      => json_encode($result),
                    ];
                }
            }

            $iterations++;
        } while ($hasToolCalls && $iterations < $maxIterations);

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
            $httpResponse = $client->post(config('assistant.openrouter.base_url') . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'HTTP-Referer'  => config('app.url', 'https://subterra.world'),
                    'X-Title'       => 'Subterra',
                    'Accept'        => 'text/event-stream',
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => config('assistant.openrouter.model'),
                    'messages'    => $messages,
                    'tools'       => $tools,
                    'tool_choice' => 'auto',
                    'max_tokens'  => (int) config('assistant.openrouter.max_tokens', 2048),
                    'temperature' => (float) config('assistant.openrouter.temperature', 0.7),
                    'stream'      => true,
                ],
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

        $body         = $httpResponse->getBody();
        $buffer       = '';
        $content      = '';
        $toolCallsMap = [];
        $finishReason = null;
        $toolCallsSeen = false;
        $usage        = null;

        while (!$body->eof()) {
            $buffer .= $body->read(128);

            // Process all complete SSE lines in the buffer
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line   = substr($buffer, 0, $pos);
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

                $choice       = $chunk['choices'][0] ?? [];
                $delta        = $choice['delta'] ?? [];
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
                                'id'       => '',
                                'type'     => 'function',
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
                'message'       => $assistantMessage,
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
     * @param  array<string, mixed>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    private function callOpenRouter(array $messages, array $tools, string $apiKey): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'HTTP-Referer'  => config('app.url', 'https://subterra.world'),
            'X-Title'       => 'Subterra',
        ])
            ->timeout(60)
            ->post(config('assistant.openrouter.base_url') . '/chat/completions', [
                'model'       => config('assistant.openrouter.model'),
                'messages'    => $messages,
                'tools'       => $tools,
                'tool_choice' => 'auto',
                'max_tokens'  => (int) config('assistant.openrouter.max_tokens', 2048),
                'temperature' => (float) config('assistant.openrouter.temperature', 0.7),
            ]);

        if ($response->status() === 429) {
            Log::warning('AssistantService: OpenRouter rate limit reached');
            throw new \RuntimeException(
                'The AI service is currently busy. Please wait a moment and try again.'
            );
        }

        if (!$response->successful()) {
            Log::error('AssistantService: OpenRouter API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
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
                'tool'  => $name,
                'error' => $e->getMessage(),
            ]);

            return ['error' => "Tool {$name} encountered an error: " . $e->getMessage()];
        }
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
            'role'    => 'system',
            'content' => "[SAFETY ALERT] River gauge(s) [{$gaugeNames}] are currently HIGH near {$caveName}. "
                . "You MUST warn the user clearly about serious flood risk before recommending this cave. "
                . "High antecedent rainfall can keep streamway caves flooded for 24-48 hours after rain stops, "
                . "even when today's forecast appears dry. Do not downplay this risk.",
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
            in_array($month, [12, 1, 2], true)  =>
                "It is currently winter in the UK. Many upland caves (Dales, Brecon, Mendip) will "
                . "have elevated water levels from heavy rainfall and snowmelt. Conditions in streamway "
                . "caves such as Lancaster Hole, Kingsdale Master Cave, and Dan-yr-Ogof are particularly "
                . "affected. Dry caves and show caves remain accessible year-round.",

            in_array($month, [3, 4, 5], true)   =>
                "It is currently spring in the UK. Conditions are transitioning — early spring often "
                . "brings high antecedent rainfall; late spring usually dries out. Snowmelt in March/April "
                . "can cause sudden rises in Yorkshire and Scottish cave streams. Always check gauge levels "
                . "before entering any streamway.",

            in_array($month, [6, 7, 8], true)   =>
                "It is currently summer in the UK. Cave conditions are generally at their best — lower "
                . "water levels make streamway systems like Lancaster Hole, Pippikin Pot, and Swildon's Hole "
                . "more accessible. However, summer thunderstorms can cause rapid flooding with little warning; "
                . "always check the forecast and have an escape plan.",

            in_array($month, [9, 10, 11], true) =>
                "It is currently autumn in the UK. Conditions deteriorate as rainfall increases through "
                . "October and November. Early autumn is often still good caving weather, but from mid-October "
                . "onwards streamway caves carry more water. Check antecedent rainfall carefully — heavy rain "
                . "24-48 hours prior significantly raises flood risk in phreatic and streamway systems.",

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

**Know the user.** Always call get_user_experience before making recommendations so you can match
suggestions to their background and avoid recommending caves they have already visited.

**Be accurate.** Only describe caves using data returned by your tools. Do not use your general
knowledge to invent details, grades, lengths, or hazard information about specific caves.

**Recent trips and activity.** When a user asks about recent trips, recent visits, recent activity,
or who has been to a specific cave system, use get_cave_system_activity (not search_caves).
Only use search_caves to find or filter cave systems — not to look up visit history.
When using search_caves solely to retrieve a cave system's ID for use with another tool, always
provide a specific name or region filter to avoid returning large unrelated result sets.

**Safety first.** For any cave that is a streamway, sump, rising phreatic, or has known flood
potential, you MUST call get_weather_forecast before recommending it. If river gauge state is
"High" or antecedent_rain_7d_mm exceeds 30mm, warn the user clearly — even if today's forecast
looks dry.

**Always mention access.** If a cave's access_info field is populated, or if get_upcoming_permits
returns has_permit: true, always surface this before the user commits to the trip.

**Link cave systems.** When you reference a specific cave system, format it as a markdown link:
[System Name](/cave-systems/{slug}). The app will render this as a clickable in-app navigation link.

**Tag search links.** When you mention a type of cave (e.g. streamway, sporting, through trip, beginner,
SRT), link to the filtered search so the user can browse all matching caves:
[streamway caves](/caves?tags=streamway&view=list). You may use any tag you have seen in tool results.

**Show locations on a map.** When you return multiple cave systems that have coordinates, include a
GeoJSON code block so the user sees them plotted on an interactive map. Only use coordinates that
appear in tool results — never fabricate them. Format:

\`\`\`geojson
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "geometry": { "type": "Point", "coordinates": [longitude, latitude] },
      "properties": { "name": "Cave System Name", "slug": "cave-system-slug" }
    }
  ]
}
\`\`\`

**Weekend planning.** When asked to plan a weekend, collect the target region, dates, and group
size (ask if not given). Use search_caves, get_cave_details, get_weather_forecast, find_nearby_huts,
and get_upcoming_permits together. Present a structured itinerary in markdown.

**Be conversational.** Ask one clarifying question at a time if needed. Keep responses focused
and practical — cavers want useful information, not essays.

**Disclaimer.** Always note that AI recommendations are a starting point. Users should verify
conditions, access, and gear requirements before committing to a trip.
PROMPT;
    }
}
