<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IncidentController extends Controller
{
    /**
     * List incidents (dashboard).
     */
    public function index()
    {
        // Get Open incidents first, then Resolved
        $incidents = Incident::with(['callout', 'controller', 'callout.user', 'callout.cave'])
            ->orderByRaw("CASE status WHEN 'open' THEN 1 WHEN 'managed' THEN 2 WHEN 'resolved' THEN 3 ELSE 4 END")
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        return response()->json(['data' => $incidents]);
    }

    /**
     * Show single incident (War Room).
     */
    public function show($id)
    {
        $incident = Incident::with([
            'callout.cave.tags',
            'callout.exitCave',
            'callout.user',
            'callout.participants',
            'controller',
            'notes.user',
        ])->findOrFail($id);

        return response()->json(['data' => $incident]);
    }

    /**
     * Acknowledge responsibility for an incident.
     */
    public function acknowledge($id)
    {
        $incident = Incident::findOrFail($id);

        if ($incident->incident_controller_id) {
            return response()->json(['message' => 'Incident already acknowledged by '.$incident->controller->name], 409);
        }

        $incident->update([
            'incident_controller_id' => Auth::id(),
            'acknowledged_at' => now(),
            'status' => 'managed',
        ]);

        // Auto-note
        $incident->notes()->create([
            'user_id' => Auth::id(),
            'content' => 'Acknowledged incident. Assuming Controller role.',
        ]);

        return response()->json(['data' => $incident->load('controller')]);
    }

    /**
     * Add a note (or police log).
     */
    public function addNote(Request $request, $id)
    {
        $incident = Incident::findOrFail($id);

        $data = $request->validate([
            'content' => 'required|string',
            'police_log_number' => 'nullable|string',
        ]);

        if (isset($data['police_log_number'])) {
            $incident->update(['police_log_number' => $data['police_log_number']]);
        }

        $note = $incident->notes()->create([
            'user_id' => Auth::id(),
            'content' => $data['content'],
        ]);

        return response()->json(['data' => $note]);
    }

    /**
     * Resolve the incident & callout.
     */
    public function resolve(Request $request, $id)
    {
        $incident = Incident::findOrFail($id);

        $incident->resolve(); // Updates incident and callout

        $incident->notes()->create([
            'user_id' => Auth::id(),
            'content' => 'Incident RESOLVED. '.$request->input('notes', ''),
        ]);

        return response()->json(['message' => 'Incident resolved']);
    }
}
