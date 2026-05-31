<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
    ],

    'pirate_weather' => [
        'api_key' => env('PIRATE_WEATHER_API_KEY'),
    ],

    // Used for SMS to trip participants and primary SMS for Duty Officers
    'clicksend' => [
        'username' => env('CLICKSEND_USERNAME'),
        'api_key' => env('CLICKSEND_API_KEY'),
        'webhook_secret' => env('CLICKSEND_WEBHOOK_SECRET'),
    ],

    'betterstack' => [
        'heartbeat_url' => env('CRON_HEARTBEAT_URL'),
    ],

    'gcp_watchdog' => [
        'url' => env('GCP_WATCHDOG_URL'),
        'api_key' => env('GCP_WATCHDOG_API_KEY'),
        'test_email' => env('GCP_WATCHDOG_TEST_EMAIL', 'admin@subterra.world'),
        'test_phone' => env('GCP_WATCHDOG_TEST_PHONE'),
    ],

    'gcp' => [
        'project_id' => env('GCP_PROJECT_ID'),
        'location' => env('GCP_LOCATION', 'europe-west2'),
        'transcoder_pubsub_topic' => env('GCP_TRANSCODER_PUBSUB_TOPIC'),
        'webhook_secret' => env('GCP_WEBHOOK_SECRET'),
        'media_processing_enabled' => env('GCP_MEDIA_PROCESSING_ENABLED', true),
    ],

];
