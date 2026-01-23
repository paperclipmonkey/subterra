<?php

namespace App\Http\Controllers;

use App\Models\Hut;
use App\Models\Cave;
use App\Services\ImageProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HutController extends Controller
{
    public function __construct(
        private readonly ImageProcessingService $imageProcessingService
    ) {}

    public function index()
    {
        return Hut::with('club')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'club_id' => 'required|exists:clubs,id',
            'location_lat' => 'nullable|numeric',
            'location_lng' => 'nullable|numeric',
            'amenities' => 'nullable|array',
            'external_url' => 'nullable|url',
            'booking_info' => 'nullable|string',
        ]);

        $hut = Hut::create($validated);

        // Process image if provided
        $this->processImageField($request, $hut);

        return response()->json($hut, 201);
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

    public function update(Request $request, Hut $hut)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'club_id' => 'required|exists:clubs,id',
            'location_lat' => 'nullable|numeric',
            'location_lng' => 'nullable|numeric',
            'amenities' => 'nullable|array',
            'external_url' => 'nullable|url',
            'booking_info' => 'nullable|string',
        ]);

        $hut->update($validated);

        // Process image if provided
        $this->processImageField($request, $hut);

        return response()->json($hut);
    }

    private function processImageField(Request $request, Hut $hut): void
    {
        if ($request->has('image') && $request->input('image') !== null) {
            $imageData = $request->input('image');
            if (is_array($imageData)) {
                $filePath = $this->imageProcessingService->processAndStoreImage($imageData, 'huts');
                $hut->update(['image' => $filePath]);
            }
        } elseif ($request->has('image') && $request->input('image') === null) {
            // Explicitly remove image if null is passed
            $hut->update(['image' => null]);
        }
    }
}
