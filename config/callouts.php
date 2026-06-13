<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Callout escalation ladder
    |--------------------------------------------------------------------------
    |
    | Timings (in minutes) for the best-in-class escalation of an unacknowledged
    | overdue incident. The ladder is:
    |   T+0                      SMS + email + Slack to the on-call duty officer
    |   T+voice_after_minutes    automated voice call to the on-call duty officer
    |   every voice_repeat_...   repeat voice calls until acknowledged or capped
    |   T+voice_all_after_...    voice calls WIDEN to all duty officers (Twilio is the
    |                            only voice channel, so a ringing phone to everyone is
    |                            the hardest alert to miss — while the backup SMS still
    |                            comes from an entirely separate provider)
    |   T+unmanaged_after_...    re-alert ALL duty officers (SMS + email)
    |
    | A voice call or inbound "ACK" SMS marks the incident acknowledged and stops
    | the voice escalation.
    |
    */
    'escalation' => [
        // Minutes after an incident is created before the first voice call is placed.
        'voice_after_minutes' => (int) env('CALLOUT_VOICE_AFTER_MINUTES', 3),
        // Minimum minutes between repeat voice calls to the same incident.
        'voice_repeat_minutes' => (int) env('CALLOUT_VOICE_REPEAT_MINUTES', 3),
        // Maximum number of voice calls before we stop dialling (Slack + all-DO
        // alerts continue to carry it). 0 disables voice escalation entirely.
        'voice_max_attempts' => (int) env('CALLOUT_VOICE_MAX_ATTEMPTS', 5),
        // Minutes after an incident opens before voice calls widen from the on-call
        // duty officer to ALL duty officers (ahead of the full unmanaged escalation).
        'voice_all_after_minutes' => (int) env('CALLOUT_VOICE_ALL_AFTER_MINUTES', 12),
        // Minutes with no controller before re-alerting ALL duty officers.
        'unmanaged_after_minutes' => (int) env('CALLOUT_UNMANAGED_AFTER_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact numbers (for display)
    |--------------------------------------------------------------------------
    |
    | The numbers alerts are sent FROM. Surfaced in-app so people can save them:
    | the primary (Twilio) SMS/voice number to everyone, and both the primary and
    | the backup (TextMagic) numbers to duty officers in the admin dashboard.
    | The primary number defaults to the Twilio sender so it can't drift out of sync.
    |
    */
    'numbers' => [
        'primary_sms' => env('CALLOUT_PRIMARY_SMS_NUMBER', env('TWILIO_PHONE_NUMBER')),
        'backup_sms' => env('CALLOUT_BACKUP_SMS_NUMBER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Required configuration guard
    |--------------------------------------------------------------------------
    |
    | A callout must never be created in an environment that can't actually raise
    | the alarm. When enforce_config is true, CalloutService refuses to create a
    | callout if any of required_config is empty (e.g. missing Twilio credentials,
    | sender number, or the backup number). Disabled in the test suite, and can be
    | turned off locally via CALLOUT_ENFORCE_CONFIG=false.
    |
    */
    'enforce_config' => (bool) env('CALLOUT_ENFORCE_CONFIG', true),

    'required_config' => [
        'services.twilio.sid',
        'services.twilio.token',
        'services.twilio.from',
        'services.twilio.webhook_secret',
        'callouts.numbers.backup_sms',
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS credit / balance
    |--------------------------------------------------------------------------
    |
    | Minimum balance (in each provider's own account currency) below which we
    | treat the provider as out of credit. When enforce_config is on, a callout is
    | refused if EITHER the primary (Twilio) or backup (TextMagic) balance is below
    | its minimum. An unknown/unreachable balance never blocks (so a balance-API
    | outage can't take down callouts). Balances are cached for cache_seconds to
    | keep the provider APIs off the callout-creation hot path.
    |
    */
    'balance' => [
        'primary_min' => (float) env('CALLOUT_PRIMARY_MIN_BALANCE', 5),
        'backup_min' => (float) env('CALLOUT_BACKUP_MIN_BALANCE', 5),
        'cache_seconds' => (int) env('CALLOUT_BALANCE_CACHE_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Duty officer WhatsApp group
    |--------------------------------------------------------------------------
    |
    | Invite link to the duty officers' WhatsApp coordination group, surfaced in
    | the admin docs. WhatsApp's API does not support posting to a group
    | programmatically, so this is an invite link for DOs to join, not an
    | automated alerting channel.
    |
    */
    'whatsapp_group_url' => env('CALLOUT_WHATSAPP_GROUP_URL'),

];
