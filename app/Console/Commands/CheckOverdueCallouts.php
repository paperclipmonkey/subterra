<?php

namespace App\Console\Commands;

use App\Models\Callout;
use App\Models\Incident;
use App\Models\User;
use App\Notifications\OverdueCalloutNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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

        if ($overdueCallouts->isEmpty()) {
            // $this->info('No overdue callouts found.'); // Quiet
        }

        foreach ($overdueCallouts as $callout) {
            $this->triggerCallout($callout);
        }
    }

    private function checkImminent(): void
    {
        // Check for callouts due between 14 and 16 minutes from now (fuzzy match for cron)
        // Ensure we haven't already warned (maybe add 'warned_at' column? or cache?)
        // For simplicity in this iteration without schema changes, we rely on the 1-minute cron 
        // and a slightly wider window, but ideally we need a flag to prevent double alert.
        // Let's assume we run every minute. We check [now+15m, now+16m).
        
        $startWindow = now()->addMinutes(15);
        $endWindow = now()->addMinutes(16);

        $imminentCallouts = Callout::active()
            ->whereBetween('callout_time', [$startWindow, $endWindow])
            ->get();

        foreach ($imminentCallouts as $callout) {
            $this->warnDutyOfficer($callout);
        }
    }

    private function checkEscalation(): void
    {
        // Find open incidents created > 15 mins ago with NO controller
        // We need a way to track if we already escalated. 
        // Ideally 'escalated_at' on Incident model. For now, we can check if an 'escalation' note exists?
        
        $staleIncidents = Incident::where('status', 'open')
            ->doesntHave('controller')
            ->where('created_at', '<=', now()->subMinutes(15))
            ->whereDoesntHave('notes', function ($query) {
                // Heuristic: check if we already logged an escalation note
                $query->where('content', 'like', '%ESCALATED%');
            })
            ->get();

        foreach ($staleIncidents as $incident) {
            $this->escalateIncident($incident);
        }
    }

    private function warnDutyOfficer(Callout $callout): void
    {
        $this->info("Warning DO about imminent callout ID: {$callout->id}");
        
        // 1. Notify Duty Officer(s)
        $shift = \App\Models\OnCallShift::where('start_at', '<=', now())
            ->where('end_at', '>=', now())
            ->first();

        $notifiables = collect();

        if ($shift) {
            $notifiables->push($shift->user);
        } else {
            // Fallback: Notify all admins if no shift coverage
            $notifiables = User::where('is_admin', true)->where('is_active', true)->get();
        }

        if ($notifiables->isNotEmpty()) {
            Notification::send($notifiables, new \App\Notifications\CalloutImminentNotification($callout));
        }

        // 2. Notify Callout Contact (User/Participants) - NEW
        if ($callout->user) {
             // Only notify the creator for now, or maybe the emergency contact? 
             // Requirement: "both the DO and the contact for the callout party"
             // Assuming "contact" refers to the user who created it (the caver), advising them to check in.
             
             // We use a new notification class for this specific messaging.
             $callout->user->notify(new \App\Notifications\CalloutImminentContactNotification($callout));
             $this->info("Sent imminent warning to user ID: {$callout->user->id}");
        }
    }

    private function escalateIncident(Incident $incident): void
    {
        DB::transaction(function () use ($incident) {
            $this->info("Escalating Incident ID: {$incident->id}");

            // 1. Notify ALL Duty Officers (Admins)
            $admins = User::where('is_admin', true)->where('is_active', true)->get();
            
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new \App\Notifications\IncidentEscalatedNotification($incident));
            }

            // 2. Log Note to prevent re-escalation
            $incident->notes()->create([
                'user_id' => null, // System
                'content' => 'SYSTEM ALERT: Incident ESCALATED. Notification sent to all Duty Officers due to 15m idle time.'
            ]);
        });
    }

    private function triggerCallout(Callout $callout): void
    {
        DB::transaction(function () use ($callout) {
            $this->info("Triggering callout ID: {$callout->id}");

            // 1. Update status
            $callout->update(['status' => 'triggered']);

            // 2. Create Incident if not exists
            $incident = Incident::firstOrCreate(
                ['callout_id' => $callout->id],
                ['status' => 'open']
            );
            
            // Reload callout with incident relationship for notification
            $callout->refresh();

            // 3. Notify Admins (Trigger Alert)
             $shift = \App\Models\OnCallShift::where('start_at', '<=', now())
                ->where('end_at', '>=', now())
                ->first();

            $notifiables = collect();
            if ($shift) {
                $notifiables->push($shift->user);
            }
            
            $admins = User::where('is_admin', true)->where('is_active', true)->get();
            
            if ($admins->isEmpty()) {
                Log::emergency("Callout triggered (ID: {$callout->id}) but NO ADMINS FOUND to notify!");
            } else {
                Notification::send($admins, new OverdueCalloutNotification($callout));
                $this->info("Sent notifications to " . $admins->count() . " admins.");
            }

            // 4. Send Slack Alert
            try {
                $caveName = $callout->cave ? $callout->cave->name : 'Unknown Location';
                $msg = "🚨 *OVERDUE CALLOUT TRIGGERED*\nLocation: *{$caveName}*\nUser: *{$callout->user->name}*\nDue: {$callout->callout_time->format('H:i')}\n<" . url('/admin/incidents/' . $incident->id) . "|View Incident>";
                
                \Spatie\SlackAlerts\Facades\SlackAlert::to('callouts-overdue')->message("<!channel>\n" . $msg);
            } catch (\Exception $e) {
                Log::error("Failed to send Overdue Slack Alert: " . $e->getMessage());
            }
        });
    }
}
