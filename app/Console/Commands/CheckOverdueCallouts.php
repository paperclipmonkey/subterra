<?php

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

        // 3. Check for Stale Incidents (Escalation)
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
        $startWindow = now()->addMinutes(14);
        $endWindow = now()->addMinutes(16);

        $imminentCallouts = Callout::active()
            ->whereNull('warned_at')
            ->where('callout_time', '>', $startWindow)
            ->where('callout_time', '<=', $endWindow)
            ->get();

        foreach ($imminentCallouts as $callout) {
            $this->warnDutyOfficer($callout);
            $callout->update(['warned_at' => now()]);
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

        $notifiables = $this->getNotifiableDutyOfficers();

        if ($notifiables->isNotEmpty()) {
            Notification::send($notifiables, new CalloutImminentNotification($callout));
        }

        $participants = $callout->participants;
        if ($participants->isNotEmpty()) {
            Notification::send($participants, new CalloutImminentContactNotification($callout));
        }
    }

    private function escalateIncident(Incident $incident): void
    {
        DB::transaction(function () use ($incident) {
            $this->info("Escalating Incident ID: {$incident->id}");

            $admins = $this->getAllDutyOfficers();

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new UnmanagedIncidentNotification($incident));
            }

            $incident->update(['escalated_at' => now()]);

            $incident->notes()->create([
                'user_id' => null,
                'content' => 'SYSTEM ALERT: Incident ESCALATED. Notification sent to all Duty Officers due to 15m idle time.',
            ]);
        });
    }

    private function triggerCallout(Callout $callout): void
    {
        DB::transaction(function () use ($callout) {
            $this->info("Triggering callout ID: {$callout->id}");

            $callout->update(['status' => 'triggered']);

            $incident = Incident::firstOrCreate(
                ['callout_id' => $callout->id],
                ['id' => str()->random(6), 'status' => 'open']
            );

            $callout->refresh();

            $notifiables = $this->getNotifiableDutyOfficers();

            if ($notifiables->isEmpty()) {
                Log::emergency("Callout triggered (ID: {$callout->id}) but NO ADMINS FOUND to notify!");
            } else {
                Notification::send($notifiables, new OverdueCalloutNotification($callout));
            }

            $participants = $callout->participants;
            if ($participants->isNotEmpty()) {
                Notification::send($participants, new CalloutOverdueContactNotification($callout));
            }

            try {
                $caveName = $callout->cave_name;
                $msg = "🚨 *OVERDUE CALLOUT TRIGGERED*\nLocation: *{$caveName}*\nUser: *{$callout->user->name}*\nDue: {$callout->callout_time->timezone(config('app.display_timezone'))->format('H:i')}\n<".url('/admin/incidents/'.$incident->id).'|View Incident>';

                SlackAlert::to('callouts-overdue')->message("<!channel>\n".$msg);
            } catch (\Exception $e) {
                Log::error('Failed to send Overdue Slack Alert: '.$e->getMessage());
            }
        });
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
     * Get all active duty officers.
     */
    private function getAllDutyOfficers(): \Illuminate\Support\Collection
    {
        return User::whereHas('roles', function ($query) {
            $query->whereIn('slug', ['duty_officer']);
        })->where('is_active', true)->get();
    }
}
