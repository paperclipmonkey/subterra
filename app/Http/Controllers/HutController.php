<?php

namespace App\Http\Controllers;

use App\Models\Hut;
use App\Services\ImageProcessingService;
use Illuminate\Http\Request;

class HutController extends Controller
{
    public function __construct(
        private readonly ImageProcessingService $imageProcessingService
    ) {
    }

    public function index()
    {
        return Hut::with('club')->get();
    }

    public function store(Request $request)
    {
        if (!$request->user() || !$request->user()->is_admin) {
            abort(403, 'Only admins can create huts.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'club_id' => 'nullable|exists:clubs,id',
            'reciprocal_clubs' => 'nullable|array',
            'reciprocal_clubs.*' => 'exists:clubs,id',
            'location_lat' => 'nullable|numeric',
            'location_lng' => 'nullable|numeric',
            'amenities' => 'nullable|array',
            'external_url' => 'nullable|url',
            'booking_info' => 'nullable|string',
        ]);

        $hut = Hut::create($validated);

        if (isset($validated['reciprocal_clubs'])) {
            $hut->reciprocalClubs()->sync($validated['reciprocal_clubs']);
        }

        // Process image if provided
        $this->processImageField($request, $hut);

        return response()->json($hut, 201);
    }

    public function show(Hut $hut)
    {
        $hut->load(['club', 'reciprocalClubs']);

        return $hut;
    }

    public function update(Request $request, Hut $hut)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $isClubAdmin = $hut->club_id && $user->clubs()->where('club_id', $hut->club_id)->wherePivot('is_admin', true)->exists();

        if (!$user->is_admin && !$isClubAdmin) {
            abort(403, 'You do not have permission to edit this hut.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'club_id' => 'nullable|exists:clubs,id',
            'reciprocal_clubs' => 'nullable|array',
            'reciprocal_clubs.*' => 'exists:clubs,id',
            'location_lat' => 'nullable|numeric',
            'location_lng' => 'nullable|numeric',
            'amenities' => 'nullable|array',
            'external_url' => 'nullable|url',
            'booking_info' => 'nullable|string',
        ]);

        $hut->update($validated);

        if (isset($validated['reciprocal_clubs'])) {
            $hut->reciprocalClubs()->sync($validated['reciprocal_clubs']);
        }

        // Process image if provided
        $this->processImageField($request, $hut);

        return response()->json($hut);
    }

    private function processImageField(Request $request, Hut $hut): void
    {
        $hasField = $request->has('image');
        $imageData = $request->input('image', []);
        $fileData = $request->file('image.data');

        if ($fileData) {
            $imageData['data'] = $fileData;
            $filePath = $this->imageProcessingService->processAndStoreImage($imageData, 'huts');
            $hut->update(['image' => $filePath]);
        } elseif ($request->has('image') && $request->input('image') === null) {
            // Explicitly remove image if null is passed
            $hut->update(['image' => null]);
        }
    }

    public function destroy(Request $request, Hut $hut)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $isClubAdmin = $hut->club_id && $user->clubs()->where('club_id', $hut->club_id)->wherePivot('is_admin', true)->exists();
        if (!$user->is_admin && !$isClubAdmin) {
            abort(403, 'You do not have permission to delete this hut.');
        }

        $hut->delete();

        return response()->json(null, 204);
    }
}
