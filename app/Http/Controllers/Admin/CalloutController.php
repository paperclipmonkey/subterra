<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Callout;
use Illuminate\Http\Request;

class CalloutController extends Controller
{
    public function index()
    {
        $callouts = Callout::with('cave', 'exitCave', 'participants')
            ->whereIn('status', ['active', 'triggered']) // Fetch both so we can show complete picture, or just active? 
            // User request: "active callouts ... as well as flagging those that have turned into active incidents"
            // If I fetch triggered here, they duplicate what IncidentController fetches.
            // But it might be cleaner to have a "Live Operations" view here. 
            // Let's fetch 'active' and 'triggered'.
            ->orderBy('callout_time', 'asc')
            ->get();

        $data = $callouts->map(function ($callout) {
            return [
                'id' => $callout->id,
                'status' => $callout->status,
                'cave_name' => $callout->cave ? $callout->cave->name : $callout->description,
                'exit_cave_name' => $callout->exitCave ? $callout->exitCave->name : null,
                'callout_time' => $callout->callout_time,
                // 'expected_exit_time' => $callout->expected_exit_time, // Removed as per user request (invalid/irrelevant)
                'team_size' => $callout->participants->count(), // User says +1 was wrong, so likely participants table includes everyone.
                'has_incident' => $callout->status === 'triggered',
                'incident_id' => $callout->incident ? $callout->incident->id : null,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
