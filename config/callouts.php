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
    |   T+voice_after_minutes    automated voice call (press 1 to acknowledge)
    |   every voice_repeat_...   repeat voice calls until acknowledged or capped
    |   T+unmanaged_after_...    re-alert ALL duty officers (existing behaviour)
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
        // Minutes with no controller before re-alerting ALL duty officers.
        'unmanaged_after_minutes' => (int) env('CALLOUT_UNMANAGED_AFTER_MINUTES', 15),
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
