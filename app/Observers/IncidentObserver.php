<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Incident;
use Illuminate\Support\Facades\DB;
use Spatie\SlackAlerts\Facades\SlackAlert;

class IncidentObserver
{
    /**
     * Handle the Incident "updated" event.
     */
    public function updated(Incident $incident): void
    {
        // Check if Status Changed
        if ($incident->wasChanged('status')) {
            // Capture the status now; dispatch only after the surrounding transaction
            // (if any) commits, so a rolled-back update never posts a phantom alert.
            $status = $incident->status;

            DB::afterCommit(function () use ($incident, $status) {
                $msg = "📢 *Incident #{$incident->id} Update*\nStatus: *{$status}*\n<".url('/admin/incidents/'.$incident->id).'|View Incident>';
                $this->sendToOverdueChannel($msg);
            });
        }
    }

    private function sendToOverdueChannel(string $msg)
    {
        try {
            SlackAlert::to('callouts-overdue')->message($msg);
        } catch (\Exception $e) {
            // Log?
        }
    }
}
