<?php

declare(strict_types=1);

return [

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        // anthropic/claude-3.5-haiku (the old default) no longer has any
        // OpenRouter endpoints. Gemini 3.5 Flash Lite is the cheapest model
        // that handled the importer's tool loop well; see the model
        // recommendations linked from the PR that introduced the importer.
        'model' => env('ASSISTANT_MODEL', 'google/gemini-3.5-flash-lite'),
        'max_tokens' => 2048,
        'temperature' => 0.7,
        // OpenAI's reasoning models (GPT-5 and later) reject `temperature`;
        // set this to false when using one.
        'send_temperature' => env('ASSISTANT_SEND_TEMPERATURE', true),
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
        // Users' trip logs and their companions' names are sent to the model,
        // so only route to providers that don't train on or store prompts.
        'deny_data_collection' => env('ASSISTANT_PROVIDER_DENY_DATA_COLLECTION', true),
        // Stricter still: zero-data-retention endpoints only.
        'zdr' => env('ASSISTANT_PROVIDER_ZDR', false),
    ],

    // Enable SSE token streaming for the final response. Set to false in test environments.
    'streaming' => env('ASSISTANT_STREAMING', true),

    // Verbose request/response logging to the standard Laravel log channel
    // (storage/logs/laravel.log). Every entry is prefixed `[Pip]` so you can
    // tail and grep, e.g. `tail -f storage/logs/laravel.log | grep '\[Pip\]'`.
    // Off by default — turn on for dev debugging only; logs include user
    // messages, model output, and tool args/results.
    'verbose_logging' => env('ASSISTANT_VERBOSE_LOGGING', false),

    // Limits for the trip importer — the default mode, and the only one open
    // to ordinary Pip users. Matching and validation are done server-side, so
    // the model needs few round-trips and short replies; a tight budget keeps
    // a cheap model on task and caps the cost of any one user.
    'limits' => [
        // Maximum conversation messages sent to the model in one request. The
        // import's state lives in the database and is summarised into the
        // system prompt, so older turns aren't needed.
        'max_history_messages' => 12,
        // Maximum user messages in one conversation. Beyond this the user is
        // asked to start a new one — their import carries over.
        'max_user_turns' => (int) env('ASSISTANT_MAX_USER_TURNS', 15),
        // Maximum LLM→tool→LLM iterations before forcing a final text answer.
        'max_tool_iterations' => 4,
        // Hard cap on TOTAL individual tool dispatches in one turn (vs the
        // iter cap, which limits LLM round-trips). Without this, models that
        // batch many parallel calls per iteration can blow past the iter cap
        // in 1-2 rounds and rack up 200s+ of latency before forced recovery.
        'max_total_tool_calls' => 8,
        'max_tokens' => 1024,
        // Low: this is data entry, not creative writing.
        'temperature' => 0.2,
    ],

    // Limits for the original trip-planning assistant (platform admins only).
    'plan_limits' => [
        'max_history_messages' => 20,
        'max_user_turns' => null,
        // 6 covers richer planning flows (user_exp → search → details →
        // weather → huts → routes) plus a little slack for the model to
        // recover from a 0-result hint. Anything higher and small models
        // spiral on duplicate searches; anything lower and good answers get
        // truncated mid-thought.
        'max_tool_iterations' => 6,
        'max_total_tool_calls' => 10,
    ],

    // Per-user spend cap, on top of the 50 requests/day rate limit: total
    // prompt + completion tokens a user may use per (UK) day across all modes.
    // 0 disables it. Platform admins are exempt.
    'budget' => [
        'daily_tokens' => (int) env('ASSISTANT_DAILY_TOKEN_BUDGET', 400000),
    ],

    'import' => [
        // Rows one import may hold (several uploads can add to it).
        'max_rows' => 1000,
        // Trips created per import_ready_trips call, to bound request time.
        'max_commit_per_call' => 250,
    ],

    // Limits for the admin data-steward mode. Data curation sessions are long
    // (scan → investigate → propose, across many records and many turns), so
    // this mode gets a far bigger budget than the trip-planning assistant.
    // A `null` value means unlimited. The tool caps are kept finite — even
    // generous ones — purely as a runaway-loop guard.
    'data_limits' => [
        'max_history_messages' => null,
        'max_user_turns' => null,
        'max_tool_iterations' => 30,
        'max_total_tool_calls' => 60,
    ],

];
