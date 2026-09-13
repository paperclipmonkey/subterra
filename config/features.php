<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default callout access for new users
    |--------------------------------------------------------------------------
    |
    | When enabled, every newly-registered user is automatically granted the
    | `callout_access` role, so they can use the callout feature by default
    | (subject to the usual approved-club + verified-phone requirements).
    |
    | This flag ONLY affects the default for brand-new accounts. Existing users
    | are never changed by it — their access continues to be managed per-user
    | via the admin users page (the "Grant / Revoke callout access" toggle), so
    | you can still turn callouts off for individual users.
    |
    */

    'callout_access_default' => env('FEATURE_CALLOUT_ACCESS_DEFAULT', true),

    /*
    |--------------------------------------------------------------------------
    | Callouts, globally
    |--------------------------------------------------------------------------
    |
    | The master switch for the whole callout feature. When disabled, nobody
    | can use callouts regardless of their roles: the UI hides every entry
    | point (nav item, cave page actions, the onboarding phone step) and the
    | callout API refuses requests, so the feature can't be reached by a
    | remembered URL or a stale client either.
    |
    | Unlike `callout_access_default` — which only sets the default for *new*
    | accounts and leaves everyone else alone — this applies to every user
    | immediately.
    |
    */

    'callouts' => env('FEATURE_CALLOUTS', true),

];
