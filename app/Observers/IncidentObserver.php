<?php

namespace App\Observers;

use App\Models\Incident;
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
            $msg = "📢 *Incident #{$incident->id} Update*\nStatus: *{$incident->status}*\n<" . url('/admin/incidents/' . $incident->id) . "|View Incident>";
            $this->sendToOverdueChannel($msg);
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
