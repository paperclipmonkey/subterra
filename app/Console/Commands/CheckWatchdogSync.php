<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Callout;
use App\Services\GcpWatchdogService;
use App\Services\Sms\SmsBalanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\SlackAlerts\Facades\SlackAlert;

/**
 * Monitors the monitor.
 *
 * The callout system's safety guarantee rests on two independent layers (the Subterra
 * scheduler and the GCP backup watchdog). If the backup silently drifts out of sync —
 * or stops responding — we lose redundancy without anyone noticing. This command
 * continuously checks that the backup is present and tracking the same active callouts,
 * and raises a Slack alert the moment it isn't.
 */
class CheckWatchdogSync extends Command
{
    protected $signature = 'callouts:check-watchdog-sync';

    protected $description = 'Verify the GCP backup watchdog is reachable and tracking the same active callouts as Subterra.';

    public function handle(GcpWatchdogService $watchdog, SmsBalanceService $balances): int
    {
        $alerts = [];

        $activeCount = Callout::active()->count();
        $watchdogCount = $watchdog->getActiveWatchdogCount();

        if ($watchdogCount === -2) {
            // Watchdog is not configured at all. Only worth surfacing if callouts are
            // actively relying on a backup that does not exist.
            if ($activeCount > 0) {
                $alerts[] = "⚠️ GCP Watchdog is NOT CONFIGURED, but {$activeCount} active callout(s) are relying on it as an independent backup.";
            }
        } elseif ($watchdogCount === -1) {
            $alerts[] = "🔴 GCP Watchdog is UNREACHABLE — the independent backup monitor cannot be contacted. {$activeCount} active callout(s) currently depend on the Subterra scheduler alone.";
        } elseif ($watchdogCount !== $activeCount) {
            $alerts[] = "🔴 Watchdog OUT OF SYNC — Subterra has {$activeCount} active callout(s) but the GCP Watchdog is tracking {$watchdogCount}. Backup coverage may be incomplete.";
        }

        // Active callouts whose watchdog registration failed at creation time (recorded
        // by CalloutService via the watchdog_registered_at column). These are still
        // monitored by the primary scheduler but have no independent backup.
        $uncovered = Callout::active()->whereNull('watchdog_registered_at')->get();
        if ($uncovered->isNotEmpty()) {
            $ids = $uncovered->pluck('id')->implode(', ');
            $alerts[] = "⚠️ {$uncovered->count()} active callout(s) have NO backup watchdog coverage (registration failed): {$ids}. Monitored by the Subterra scheduler only.";
        }

        // Proactively catch low SMS credit before anyone tries to set a callout. Auto-top-up
        // should keep this from ever firing — this is the belt-and-braces alert if it does.
        foreach ($balances->providerStatuses() as $status) {
            if ($status['reachable'] && !$status['ok']) {
                $alerts[] = "🔴 {$status['provider']} SMS credit is LOW ({$status['amount']} {$status['currency']}, below the {$status['minimum']} minimum) — NEW CALLOUTS ARE BLOCKED. Auto-top-up should prevent this; check the account now.";
            }
        }

        if (empty($alerts)) {
            $this->info("Watchdog in sync: {$activeCount} active callout(s), {$watchdogCount} tracked by the backup watchdog.");

            return self::SUCCESS;
        }

        foreach ($alerts as $alert) {
            $this->error($alert);
            Log::error('[Watchdog Monitor] '.$alert);
        }

        try {
            SlackAlert::to('callouts-overdue')->message("*🛰️ Watchdog Monitor*\n".implode("\n", $alerts));
        } catch (\Throwable $e) {
            Log::error('Failed to send watchdog-sync Slack alert: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
