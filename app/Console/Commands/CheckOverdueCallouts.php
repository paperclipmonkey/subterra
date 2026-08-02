<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Callout;
use App\Models\Incident;
use App\Models\OnCallShift;
use App\Models\User;
use App\Notifications\CalloutImminentContactNotification;
use App\Notifications\CalloutImminentNotification;
use App\Notifications\CalloutOverdueContactNotification;
use App\Notifications\OverdueCalloutNotification;
use App\Notifications\UnmanagedIncidentNotification;
use App\Services\GcpWatchdogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\SlackAlerts\Facades\SlackAlert;

class CheckOverdueCallouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'callouts:check-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for open callouts that have passed their panic time and trigger them.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // 1. Check for Imminent Callouts (15 mins warning)
        $this->checkImminent();

        // 2. Check for Overdue Callouts (Trigger)
        $this->checkOverdue();

        // 3. Voice-call escalation for unacknowledged incidents (press 1 to acknowledge)
        $this->checkVoiceEscalation();

        // 4. Check for Stale Incidents (re-alert ALL duty officers)
        $this->checkEscalation();
    }

    private function checkOverdue(): void
    {
        $overdueCallouts = Callout::active()
            ->dueBefore(now())
            ->with(['user', 'cave'])
            ->get();

        foreach ($overdueCallouts as $callout) {
            $this->triggerCallout($callout);
        }
    }

    private function checkImminent(): void
    {
        // Warn for anything due within the next 16 minutes that hasn't been warned yet
        // and isn't already overdue (checkOverdue handles those). A fixed two-minute
        // (now+14, now+16] window would be skipped permanently whenever the scheduler
        // missed a couple of ticks; warned_at keeps this idempotent instead.
        $imminentCallouts = Callout::active()
            ->whereNull('warned_at')
            ->where('callout_time', '>', now())
            ->where('callout_time', '<=', now()->addMinutes(16))
            ->get();

        foreach ($imminentCallouts as $callout) {
            // Mark as warned BEFORE notifying so a notification failure cannot cause the
            // same imminent warning to be re-sent on the next run.
            $callout->update(['warned_at' => now()]);
            $this->warnDutyOfficer($callout);
        }
    }

    private function checkEscalation(): void
    {
        $staleIncidents = Incident::where('status', 'open')
            ->doesntHave('controller')
            ->where('created_at', '<=', now()->subMinutes(15))
            ->whereNull('escalated_at')
            ->get();

        foreach ($staleIncidents as $incident) {
            $this->escalateIncident($incident);
        }
    }

    private function warnDutyOfficer(Callout $callout): void
    {
        $this->info("Warning DO about imminent callout ID: {$callout->id}");

        // warned_at is set by the caller BEFORE we notify, so a notification failure
        // here can never cause the same warning to fire again on the next run.
        $notifiables = $this->getNotifiableDutyOfficers();

        if ($notifiables->isNotEmpty()) {
            $this->safeNotify($notifiables, new CalloutImminentNotification($callout), "imminent DO warning for callout {$callout->id}");
        }

        $participants = $callout->participants;
        if ($participants->isNotEmpty()) {
            $this->safeNotify($participants, new CalloutImminentContactNotification($callout), "imminent participant warning for callout {$callout->id}");
        }
    }

    /**
     * Voice-call escalation rung: for incidents that remain unacknowledged after a short
     * delay, place an automated "press 1 to acknowledge" call — repeating (and widening to
     * all duty officers) until acknowledged or the attempt cap is reached. A ringing phone
     * is far harder to miss than a silent SMS, and repeat calls break through Do-Not-Disturb.
     */
    private function checkVoiceEscalation(): void
    {
        $cfg = config('callouts.escalation');
        $maxAttempts = (int) ($cfg['voice_max_attempts'] ?? 0);

        if ($maxAttempts <= 0) {
            return; // voice escalation disabled
        }

        $afterMinutes = (int) ($cfg['voice_after_minutes'] ?? 3);
        $repeatMinutes = (int) ($cfg['voice_repeat_minutes'] ?? 3);

        $incidents = Incident::where('status', 'open')
            ->doesntHave('controller')
            ->whereNull('acknowledged_at')
            ->where('created_at', '<=', now()->subMinutes($afterMinutes))
            ->where('voice_call_count', '<', $maxAttempts)
            ->where(function ($q) use ($repeatMinutes) {
                $q->whereNull('last_voice_call_at')
                    ->orWhere('last_voice_call_at', '<=', now()->subMinutes($repeatMinutes));
            })
            ->with('callout')
            ->get();

        foreach ($incidents as $incident) {
            try {
                $this->placeVoiceCalls($incident);
            } catch (\Throwable $e) {
                Log::error("Voice escalation failed for incident {$incident->id}: {$e->getMessage()}");
            }
        }
    }

    private function placeVoiceCalls(Incident $incident): void
    {
        // The on-call DO gets the calls to themselves at first (repeating). Voice calls
        // then widen to ALL duty officers once the incident reaches voice_all_after_minutes
        // (default 12) — Twilio is the only voice channel, so ringing every phone is the
        // hardest alert to miss — or as soon as it is escalated for being unmanaged (the
        // 15-minute mark), whichever comes first. If nobody is on call at all,
        // getNotifiableDutyOfficers falls back to everyone as a safety net.
        $voiceAllAfterMinutes = (int) (config('callouts.escalation.voice_all_after_minutes') ?? 12);
        $widenToAll = $incident->escalated_at
            || $incident->created_at->lte(now()->subMinutes($voiceAllAfterMinutes));

        $recipients = $widenToAll
            ? $this->getAllDutyOfficers()
            : $this->getNotifiableDutyOfficers();

        $recipients = $recipients->filter(fn ($do) => !empty($do->phone))->values();

        if ($recipients->isEmpty()) {
            Log::warning("Voice escalation: no duty officers with a phone for incident {$incident->id}.");
            // Still record the attempt so we don't spin every minute with nobody to call.
            $incident->update(['last_voice_call_at' => now(), 'voice_call_count' => $incident->voice_call_count + 1]);

            return;
        }

        $voice = app(\App\Contracts\VoiceCaller::class);
        $secret = (string) config('services.twilio.webhook_secret') ?: 'unconfigured';
        $placed = 0;

        foreach ($recipients as $do) {
            $url = route('webhooks.twilio.voice', [
                'secret' => $secret,
                'incident' => $incident->id,
                'user' => $do->id,
            ]);

            if ($voice->call($do->phone, $url) !== null) {
                ++$placed;
            }
        }

        $attempt = $incident->voice_call_count + 1;
        $incident->update(['last_voice_call_at' => now(), 'voice_call_count' => $attempt]);

        $incident->notes()->create([
            'user_id' => null,
            'content' => "SYSTEM: Voice-call escalation attempt {$attempt} — dialled {$recipients->count()} duty officer(s) ({$placed} placed). Press 1 on the call to acknowledge.",
        ]);

        $this->info("Voice escalation attempt {$attempt} for incident {$incident->id}: {$placed}/{$recipients->count()} placed.");
    }

    private function escalateIncident(Incident $incident): void
    {
        $this->info("Escalating Incident ID: {$incident->id}");

        // Persist the escalation FIRST so a notification failure can never roll it back
        // (which would cause the incident to escalate repeatedly on every subsequent run).
        DB::transaction(function () use ($incident) {
            $incident->update(['escalated_at' => now()]);

            $incident->notes()->create([
                'user_id' => null,
                'content' => 'SYSTEM ALERT: Incident ESCALATED. Notification sent to all Duty Officers due to 15m idle time.',
            ]);
        });

        $admins = $this->getAllDutyOfficers();

        if ($admins->isNotEmpty()) {
            $this->safeNotify($admins, new UnmanagedIncidentNotification($incident), "escalation notification for incident {$incident->id}");
        }
    }

    private function triggerCallout(Callout $callout): void
    {
        $this->info("Triggering callout ID: {$callout->id}");

        // Persist the trigger + incident FIRST, inside a transaction. Notifications are
        // sent AFTER the commit so that an SMS/email provider failure can never roll back
        // the triggered status or the incident record (which would leave an overdue caver
        // with no persisted incident, and re-trigger duplicate alerts on the next run).
        $incident = DB::transaction(function () use ($callout) {
            // Atomic active -> triggered gate: a callout cancelled between our (unlocked)
            // fetch and this update must NOT be resurrected to 'triggered' and falsely
            // alert duty officers.
            $updated = Callout::query()
                ->whereKey($callout->id)
                ->where('status', 'active')
                ->update(['status' => 'triggered']);

            if ($updated === 0) {
                return;
            }

            return Incident::firstOrCreate(
                ['callout_id' => $callout->id],
                ['id' => str()->random(6), 'status' => 'open']
            );
        });

        if ($incident === null) {
            $this->info("Callout ID: {$callout->id} is no longer active; skipping trigger.");

            return;
        }

        $callout->refresh();

        $notifiables = $this->getNotifiableDutyOfficers();

        if ($notifiables->isEmpty()) {
            Log::emergency("Callout triggered (ID: {$callout->id}) but NO ADMINS FOUND to notify!");
        } else {
            $this->safeNotify($notifiables, new OverdueCalloutNotification($callout), "overdue DO notification for callout {$callout->id}");
        }

        $participants = $callout->participants;
        if ($participants->isNotEmpty()) {
            $this->safeNotify($participants, new CalloutOverdueContactNotification($callout), "overdue participant notification for callout {$callout->id}");
        }

        try {
            $caveName = $callout->cave_name;
            $msg = "🚨 *OVERDUE CALLOUT TRIGGERED*\nLocation: *{$caveName}*\nUser: *{$callout->user->name}*\nDue: {$callout->callout_time->timezone(config('app.display_timezone'))->format('H:i')}\n<".url('/admin/incidents/'.$incident->id).'|View Incident>';

            SlackAlert::to('callouts-overdue')->message("<!channel>\n".$msg);
        } catch (\Exception $e) {
            Log::error('Failed to send Overdue Slack Alert: '.$e->getMessage());
        }

        // Cancel the GCP watchdog now that Laravel has handled this callout.
        // A watchdog failure here is tolerated — a duplicate backup alert is far safer
        // than a missed one.
        try {
            app(GcpWatchdogService::class)->cancel($callout);
        } catch (\Exception $e) {
            Log::error("Failed to cancel GCP watchdog for callout {$callout->id}: {$e->getMessage()}");
        }
    }

    /**
     * Send a notification to each recipient in isolation. A failure to reach one
     * recipient (e.g. a downed SMS/email provider) is logged but never aborts the
     * remaining sends, and never propagates to roll back any surrounding DB writes.
     */
    private function safeNotify(iterable $notifiables, $notification, string $context): void
    {
        foreach ($notifiables as $notifiable) {
            try {
                Notification::send([$notifiable], $notification);
            } catch (\Throwable $e) {
                Log::error("Failed to send {$context} to a recipient: {$e->getMessage()}");
            }
        }
    }

    /**
     * Get the on-call duty officer, or fall back to all DOs.
     */
    private function getNotifiableDutyOfficers(): \Illuminate\Support\Collection
    {
        $shift = OnCallShift::where('start_at', '<=', now())
            ->where('end_at', '>=', now())
            ->first();

        if ($shift) {
            return collect([$shift->user]);
        }

        return $this->getAllDutyOfficers();
    }

    /**
     * Get all active duty officers. The rota accepts both duty officers and platform
     * admins (see Admin\OnCallController's user_id validation), so widened escalation
     * must include both — an all-platform-admin rota must never leave nobody to alert.
     */
    private function getAllDutyOfficers(): \Illuminate\Support\Collection
    {
        return User::whereHas('roles', function ($query) {
            $query->whereIn('slug', ['duty_officer', 'platform_admin']);
        })->where('is_active', true)->get();
    }
}
