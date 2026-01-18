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
    protected $description = 'Check for active callouts that have passed their panic time and trigger them.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $overdueCallouts = Callout::active()
            ->dueBefore(now())
            ->with(['user', 'cave'])
            ->get();

        if ($overdueCallouts->isEmpty()) {
            $this->info('No overdue callouts found.');
            return;
        }

        foreach ($overdueCallouts as $callout) {
            $this->triggerCallout($callout);
        }
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

            // 3. Notify Admins
            // Identify Duty Officers (Admins)
            $admins = User::where('is_admin', true)->where('is_active', true)->get();
            
            if ($admins->isEmpty()) {
                Log::emergency("Callout triggered (ID: {$callout->id}) but NO ADMINS FOUND to notify!");
            } else {
                Notification::send($admins, new OverdueCalloutNotification($callout));
                $this->info("Sent notifications to " . $admins->count() . " admins.");
            }
        });
    }
}
