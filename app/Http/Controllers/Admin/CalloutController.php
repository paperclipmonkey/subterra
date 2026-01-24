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
            // User request: "Open callouts ... as well as flagging those that have turned into active incidents"
            // If I fetch triggered here, they duplicate what IncidentController fetches.
            // But it might be cleaner to have a "Live Operations" view here. 
            // Let's fetch 'active' and 'triggered'.
            ->orderBy('callout_time', 'asc')
            ->get();

        $data = $callouts->map(function ($callout) {
            $lat = null;
            $lng = null;
            if ($callout->cave) {
                $lat = $callout->cave->location_lat;
                $lng = $callout->cave->location_lng;
            } elseif (!empty($callout->location_data) && isset($callout->location_data['latitude'], $callout->location_data['longitude'])) {
                $lat = $callout->location_data['latitude'];
                $lng = $callout->location_data['longitude'];
            }

            return [
                'id' => $callout->id,
                'status' => $callout->status,
                'cave_name' => $callout->cave ? $callout->cave->name : $callout->description,
                'exit_cave_name' => $callout->exitCave ? $callout->exitCave->name : null,
                'callout_time' => $callout->callout_time,
                'team_size' => $callout->participants->count(),
                'has_incident' => $callout->status === 'triggered',
                'incident_id' => $callout->incident ? $callout->incident->id : null,
                'lat' => $lat,
                'lng' => $lng,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
