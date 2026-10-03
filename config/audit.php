<?php

declare(strict_types=1);

return [

    'enabled' => env('AUDITING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Audit Implementation
    |--------------------------------------------------------------------------
    |
    | Define which Audit model implementation should be used.
    |
    */

    'implementation' => OwenIt\Auditing\Models\Audit::class,

    /*
    |--------------------------------------------------------------------------
    | User Morph prefix & Guards
    |--------------------------------------------------------------------------
    |
    | Define the morph prefix and authentication guards for the User resolver.
    |
    */

    'user' => [
        'morph_prefix' => 'user',
        'guards' => [
            'web',
            'api',
        ],
        'resolver' => OwenIt\Auditing\Resolvers\UserResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Resolvers
    |--------------------------------------------------------------------------
    |
    | Define the IP Address, User Agent and URL resolver implementations.
    |
    */
    'resolvers' => [
        'ip_address' => OwenIt\Auditing\Resolvers\IpAddressResolver::class,
        'user_agent' => OwenIt\Auditing\Resolvers\UserAgentResolver::class,
        'url' => OwenIt\Auditing\Resolvers\UrlResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Events
    |--------------------------------------------------------------------------
    |
    | The Eloquent events that trigger an Audit.
    |
    */

    'events' => [
        'created',
        'updated',
        'deleted',
        'restored',
    ],

    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    |
    | Enable the strict mode when auditing?
    |
    */

    'strict' => false,

    /*
    |--------------------------------------------------------------------------
    | Global exclude
    |--------------------------------------------------------------------------
    |
    | Have something you always want to exclude by default? - add it here.
    | Note that this is overwritten (not merged) with local exclude
    |
    */

    'exclude' => [],

    /*
    |--------------------------------------------------------------------------
    | Empty Values
    |--------------------------------------------------------------------------
    |
    | Should Audit records be stored when the recorded old_values & new_values
    | are both empty?
    |
    | Some events may be empty on purpose. Use allowed_empty_values to exclude
    | those from the empty values check. For example when auditing
    | model retrieved events which will never have new and old values.
    |
    |
    */

    'empty_values' => true,
    'allowed_empty_values' => [
        'retrieved',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Array Values
    |--------------------------------------------------------------------------
    |
    | Should the array values be audited?
    |
    | By default, array values are not allowed. This is to prevent performance
    | issues when storing large amounts of data. You can override this by
    | setting allow_array_values to true.
    */
    'allowed_array_values' => false,

    /*
    |--------------------------------------------------------------------------
    | Audit Timestamps
    |--------------------------------------------------------------------------
    |
    | Should the created_at, updated_at and deleted_at timestamps be audited?
    |
    */

    'timestamps' => false,

    /*
    |--------------------------------------------------------------------------
    | Audit Threshold
    |--------------------------------------------------------------------------
    |
    | Specify a threshold for the amount of Audit records a model can have.
    | Zero means no limit.
    |
    */

    // Retention is enforced by age instead, see 'retention_days' below: a
    // per-model count limit would keep a rarely-edited record's personal data
    // forever while dropping recent history of busy records.
    'threshold' => 0,

    /*
    |--------------------------------------------------------------------------
    | Audit Retention
    |--------------------------------------------------------------------------
    |
    | Audits hold personal data (old/new attribute values, IP address, user
    | agent). When set, the scheduled `audits:prune` command (routes/console.php)
    | deletes anything older than this many days. The privacy policy says logs
    | are kept for up to one year, so 365 is the expected production value.
    |
    | Off unless configured: pruning permanently deletes history (trip creators
    | are derived from their "created" audit, for example), so enabling it is a
    | deliberate deployment decision. Audits of deleted users are swept
    | regardless.
    |
    */

    'retention_days' => env('AUDIT_RETENTION_DAYS') !== null ? (int) env('AUDIT_RETENTION_DAYS') : null,

    /*
    |--------------------------------------------------------------------------
    | Audit Driver
    |--------------------------------------------------------------------------
    |
    | The default audit driver used to keep track of changes.
    |
    */

    'driver' => 'database',

    /*
    |--------------------------------------------------------------------------
    | Audit Driver Configurations
    |--------------------------------------------------------------------------
    |
    | Available audit drivers and respective configurations.
    |
    */

    'drivers' => [
        'database' => [
            'table' => 'audits',
            'connection' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Queue Configurations
    |--------------------------------------------------------------------------
    |
    | Available audit queue configurations.
    |
    */

    'queue' => [
        'enable' => false,
        'connection' => 'sync',
        'queue' => 'default',
        'delay' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Console
    |--------------------------------------------------------------------------
    |
    | Whether console events should be audited (eg. php artisan db:seed).
    |
    */

    'console' => true,
];
