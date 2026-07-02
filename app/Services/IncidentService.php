<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IncidentService
{
    /**
     * Acknowledge an incident: assign a controller, stop escalation (status -> managed),
     * and record an audit note. Shared by the admin HTTP endpoint, inbound "ACK" SMS, and
     * the voice "press 1" flow so all acknowledgement paths behave identically.
     *
     * Returns true if this call acknowledged it, false if it was already acknowledged.
     */
    public function acknowledge(Incident $incident, ?User $controller, string $source): bool
    {
        if ($incident->incident_controller_id) {
            return false;
        }

        // Atomic gate: re-check under a row lock inside a transaction so two duty
        // officers acknowledging at the same moment can't both "win" — only the first
        // becomes the Controller; the loser is routed to the already-acknowledged path.
        return DB::transaction(function () use ($incident, $controller, $source): bool {
            $locked = Incident::query()->whereKey($incident->getKey())->lockForUpdate()->first();

            if (!$locked || $locked->incident_controller_id) {
                return false;
            }

            $incident->update([
                'incident_controller_id' => $controller?->id,
                'acknowledged_at' => now(),
                'status' => 'managed',
            ]);

            $who = $controller?->name ?? 'A duty officer';
            $incident->notes()->create([
                'user_id' => $controller?->id,
                'content' => "{$who} acknowledged the incident via {$source} and is assuming the Controller role.",
            ]);

            return true;
        });
    }
}
