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

    // Twilio — primary SMS + voice for the Fly/Subterra side (the GCP backup uses
    // TextMagic, preserving cross-provider redundancy).
    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        // Outbound caller-ID / SMS sender number (E.164, e.g. +447...).
        'from' => env('TWILIO_PHONE_NUMBER'),
        // Shared secret embedded in inbound webhook URLs (Twilio can't send a custom
        // header, so the secret lives in the configured URL — like the other webhooks).
        'webhook_secret' => env('TWILIO_WEBHOOK_SECRET'),
        // Master kill-switch for real outbound SMS/voice (e.g. off in staging).
        'enabled' => env('TWILIO_ENABLED', false),
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
