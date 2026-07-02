<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\IncidentNote;
use Illuminate\Support\Facades\DB;
use Spatie\SlackAlerts\Facades\SlackAlert;

class IncidentNoteObserver
{
    /**
     * Handle the IncidentNote "created" event.
     */
    public function created(IncidentNote $note): void
    {
        // Assuming we only care about notes on OPEN incidences or all?
        // User requested: "Continue to post every update for an overdue callout"
        // Overdue callout == Incident.

        // Notes are often created inside transactions (cancel/acknowledge/trigger flows):
        // dispatch the Slack alert only after the commit so a rollback can never produce
        // a phantom alert. Outside a transaction the callback runs immediately.
        DB::afterCommit(function () use ($note) {
            $incident = $note->incident;
            $author = $note->user ? $note->user->name : 'System';

            $msg = "📝 *New Update on Incident #{$incident->id}*\nFrom: {$author}\n> {$note->content}\n<".url('/admin/incidents/'.$incident->id).'|View Incident>';

            try {
                SlackAlert::to('callouts-overdue')->message($msg);
            } catch (\Exception $e) {
                // Ignore
            }
        });
    }
}
