<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserCreated;
use Illuminate\Support\Facades\Log;

class GrantDefaultCalloutAccess
{
    /**
     * Grant newly-registered users the `callout_access` role by default, so they can
     * use the callout feature as soon as their club membership is approved and their
     * phone is verified.
     *
     * Controlled by the `features.callout_access_default` flag. This only affects new
     * accounts at signup/activation — existing users are managed individually via the
     * admin users page, so callouts can still be turned off per user.
     */
    public function handle(UserCreated $event): void
    {
        if (!config('features.callout_access_default')) {
            return;
        }

        // Mirror the signup Slack alert: only grant on genuine activation, not for the
        // dormant placeholder accounts created when someone is tagged in a trip. Those
        // fire UserCreated again when the user first signs in (and becomes active).
        if (!$event->user->is_active) {
            return;
        }

        try {
            $event->user->assignRole('callout_access');
        } catch (\Throwable $e) {
            // Never let a missing role / DB hiccup block account creation.
            Log::error('Failed to grant default callout_access role: '.$e->getMessage());
        }
    }
}
