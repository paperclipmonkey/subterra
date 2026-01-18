<?php

namespace App\Http\Controllers;

use App\Models\Hut;
use App\Models\Cave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HutController extends Controller
{
    public function index()
    {
        return Hut::with('club')->get();
    }

    public function show(Hut $hut)
    {
        $hut->load(['club', 'reciprocalClubs']);
        
        // Find nearby caves (within 10km)
        // Haversine formula
        $lat = $hut->location_lat;
        $lng = $hut->location_lng;
        
        if ($lat && $lng) {
            try {
                $nearbyCaves = Cave::select('id', 'name', 'slug', 'location_name', 'location_lat', 'location_lng')
                    ->selectRaw("(6371 * acos(cos(radians(?)) * cos(radians(location_lat)) * cos(radians(location_lng) - radians(?)) + sin(radians(?)) * sin(radians(location_lat)))) AS distance", [$lat, $lng, $lat])
                    ->having('distance', '<', 10)
                    ->orderBy('distance')
                    ->limit(10)
                    ->get();
                    
                $hut->nearby_caves = $nearbyCaves;
            } catch (\Exception $e) {
                // Fallback for when math functions aren't available (e.g. SQLite testing without extensions)
                \Illuminate\Support\Facades\Log::error('Distance calculation failed: ' . $e->getMessage());
                $hut->nearby_caves = [];
            }
        } else {
             $hut->nearby_caves = [];
        }

        return $hut;
    }
}
