<?php

return [
    /*
     * The webhook URLs that we'll use to send a message to Slack.
     */
    'webhook_urls' => [
        'signups' => env('SLACK_SIGNUPS_WEBHOOK'),
        'trips' => env('SLACK_TRIPS_WEBHOOK'),
        'corrections' => env('SLACK_CORRECTIONS_WEBHOOK'),
        'callouts' => env('SLACK_CALLOUTS_OPEN_WEBHOOK'),
        'callouts-open' => env('SLACK_CALLOUTS_OPEN_WEBHOOK'),
        'callouts-overdue' => env('SLACK_CALLOUTS_OVERDUE_WEBHOOK'),
        'approvals' => env('SLACK_CLUB_APPROVAL_WEBHOOK'),
    ],

    /*
     * This job will send the message to Slack. You can extend this
     * job to set timeouts, retries, etc...
     */
    'job' => Spatie\SlackAlerts\Jobs\SendToSlackChannelJob::class,
    'queue' => env('SLACK_ALERT_QUEUE', 'default'),
];
