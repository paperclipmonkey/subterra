<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Services\IncidentService;
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

        return IncidentResource::collection($incidents);
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

        return response()->json([
            'data' => new IncidentResource($incident),
            // Region-specific 999 / cave-rescue guidance for the Rescue Protocol script.
            'rescue_info' => app(\App\Services\CaveRescueService::class)->forCave($incident->callout?->cave),
            // Per-recipient SMS delivery for this incident's callout (most recent first).
            'sms_deliveries' => \App\Models\SmsMessage::query()
                ->where('incident_id', $incident->id)
                ->orWhere('callout_id', $incident->callout_id)
                ->orderByDesc('created_at')
                ->get(['id', 'recipient_name', 'to_masked', 'context', 'status', 'error_code', 'sent_at', 'delivered_at', 'failed_at']),
        ]);
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

        app(IncidentService::class)->acknowledge($incident, Auth::user(), 'the dashboard');

        return response()->json(['data' => new IncidentResource($incident->fresh()->load('controller'))]);
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
