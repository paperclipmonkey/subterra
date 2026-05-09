<?php

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

    'limits' => [
        // Maximum conversation turns sent to the model in one request
        'max_history_messages' => 20,
        // Maximum LLM→tool→LLM iterations before forcing a final text answer.
        // 4 is enough for typical multi-tool flows (e.g. user_exp → search → details → weather)
        // and prevents the model from spending its whole budget retrying a failed search.
        'max_tool_iterations' => 4,
    ],

];
