<?php

return [

    'openrouter' => [
        'api_key'     => env('OPENROUTER_API_KEY'),
        'base_url'    => 'https://openrouter.ai/api/v1',
        'model'       => env('ASSISTANT_MODEL', 'anthropic/claude-3-5-haiku'),
        'max_tokens'  => 2048,
        'temperature' => 0.7,
    ],

    // Enable SSE token streaming for the final response. Set to false in test environments.
    'streaming' => env('ASSISTANT_STREAMING', true),

    'limits' => [
        // Maximum conversation turns sent to the model in one request
        'max_history_messages'     => 20,
        // Maximum LLM→tool→LLM iterations before returning whatever we have
        'max_tool_iterations'      => 5,
    ],

];
