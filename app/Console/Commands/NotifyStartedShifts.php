<?php

namespace App\Console\Commands;

use App\Models\OnCallShift;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\SlackAlerts\Facades\SlackAlert;

class NotifyStartedShifts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shifts:notify-started';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Slack alerts for on-call shifts that have just started.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();

        $shifts = OnCallShift::with('user')
            ->where('start_at', '<=', $now)
            ->whereNull('notified_at')
            ->get();

        foreach ($shifts as $shift) {
            try {
                $user = $shift->user;
                $start = $shift->start_at->format('d/m H:i');
                $end = $shift->end_at->format('d/m H:i');

                $msg = "🛡️ *DUTY OFFICER UPDATE*\n{$user->name} is now ON CALL.\nFrom: {$start}\nUntil: {$end}.";

                SlackAlert::to('callouts')->message($msg);

                $shift->update(['notified_at' => $now]);

                $this->info("Notified for shift ID: {$shift->id}");
            } catch (\Exception $e) {
                Log::error('Failed to send On Call Shift Slack alert: '.$e->getMessage());
                $this->error("Failed to notify for shift ID: {$shift->id}");
            }
        }

        return Command::SUCCESS;
    }
}
