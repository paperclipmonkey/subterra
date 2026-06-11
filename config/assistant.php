<?php

declare(strict_types=1);

return [

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => 'https://openrouter.ai/api/v1',
        'model' => env('ASSISTANT_MODEL', 'anthropic/claude-3-5-haiku'),
        'max_tokens' => 2048,
        'temperature' => 0.7,
    ],

    // OpenRouter provider routing — pin which underlying provider serves the request.
    // See https://openrouter.ai/docs/provider-routing
    //
    // Set ASSISTANT_PROVIDER_ORDER to a comma-separated list of providers, in priority
    // order. Examples:
    //   ASSISTANT_PROVIDER_ORDER=Anthropic                  (Claude direct, lower latency)
    //   ASSISTANT_PROVIDER_ORDER=Groq,Together              (open-weights, very high tps)
    //   ASSISTANT_PROVIDER_ORDER=Cerebras                   (open-weights, fastest)
    //
    // Leave empty to let OpenRouter pick the cheapest available provider.
    'provider' => [
        'order' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ASSISTANT_PROVIDER_ORDER', ''))
        ))),
        'allow_fallbacks' => env('ASSISTANT_PROVIDER_ALLOW_FALLBACKS', true),
        'require_parameters' => env('ASSISTANT_PROVIDER_REQUIRE_PARAMETERS', true),
    ],

    // Enable SSE token streaming for the final response. Set to false in test environments.
    'streaming' => env('ASSISTANT_STREAMING', true),

    // Verbose request/response logging to the standard Laravel log channel
    // (storage/logs/laravel.log). Every entry is prefixed `[Pip]` so you can
    // tail and grep, e.g. `tail -f storage/logs/laravel.log | grep '\[Pip\]'`.
    // Off by default — turn on for dev debugging only; logs include user
    // messages, model output, and tool args/results.
    'verbose_logging' => env('ASSISTANT_VERBOSE_LOGGING', false),

    'limits' => [
        // Maximum conversation turns sent to the model in one request
        'max_history_messages' => 20,
        // Maximum LLM→tool→LLM iterations before forcing a final text answer.
        // 6 covers richer planning flows (user_exp → search → details →
        // weather → huts → routes) plus a little slack for the model to
        // recover from a 0-result hint. Anything higher and small models
        // spiral on duplicate searches; anything lower and good answers get
        // truncated mid-thought.
        'max_tool_iterations' => 6,
        // Hard cap on TOTAL individual tool dispatches in one turn (vs the
        // iter cap, which limits LLM round-trips). Without this, models that
        // batch many parallel calls per iteration can blow past the iter cap
        // in 1-2 rounds and rack up 200s+ of latency before forced recovery.
        'max_total_tool_calls' => 10,
    ],

    // Limits for the admin data-steward mode. Data curation sessions are long
    // (scan → investigate → propose, across many records and many turns), so
    // this mode gets a far bigger budget than the trip-planning assistant.
    // A `null` value means unlimited. The tool caps are kept finite — even
    // generous ones — purely as a runaway-loop guard.
    'data_limits' => [
        'max_history_messages' => null,
        'max_tool_iterations' => 30,
        'max_total_tool_calls' => 60,
    ],

];
