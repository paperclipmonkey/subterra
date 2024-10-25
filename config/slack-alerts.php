<?php

return [
    /*
     * The webhook URLs that we'll use to send a message to Slack.
     */
    'webhook_urls' => [
        'signups' => env('SLACK_SIGNUPS_WEBHOOK'),
        'trips' => env('SLACK_TRIPS_CHANNEL'),
    ],

    /*
     * This job will send the message to Slack. You can extend this
     * job to set timeouts, retries, etc...
     */
    'job' => Spatie\SlackAlerts\Jobs\SendToSlackChannelJob::class,
    'queue' => env('SLACK_ALERT_QUEUE', 'default'),
];
