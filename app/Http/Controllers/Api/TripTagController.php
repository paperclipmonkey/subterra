<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB; // Import DB facade

class TripTagController extends Controller
{
    /**
     * Display a listing of the assignable trip tags.
     */
    public function index(): JsonResponse
    {
        $tags = Tag::where('type', 'trip')
                   ->where('assignable', true)
                   ->select('id', 'tag', 'category', 'description') // Select specific fields
                   ->get()
                   ->groupBy('category'); // Group by category for easier frontend handling

        return response()->json($tags);
    }

    /**
     * Update the tags for a specific trip.
     */
    public function update(Request $request, Trip $trip): JsonResponse
    {
        $validated = $request->validate([
            'tags' => 'present|array', // Ensure 'tags' key exists, even if empty array
            'tags.*' => 'integer|exists:tags,id', // Validate each item is an existing tag ID
        ]);

        // Fetch the IDs of tags that are actually assignable trip tags
        $assignableTripTagIds = Tag::where('type', 'trip')
                                   ->where('assignable', true)
                                   ->whereIn('id', $validated['tags'])
                                   ->pluck('id');

        // Use transaction for atomicity
        DB::transaction(function () use ($trip, $assignableTripTagIds) {
            // Detach all existing 'trip' type tags first
            $existingTripTagIds = $trip->tags()->where('type', 'trip')->pluck('tags.id');
            $trip->tags()->detach($existingTripTagIds);

            // Attach the validated assignable trip tags
            if ($assignableTripTagIds->isNotEmpty()) {
                $trip->tags()->attach($assignableTripTagIds);
            }
        });

        // Eager load the updated tags for the response
        $trip->load(['tags' => function ($query) {
            $query->where('type', 'trip')->select('tags.id', 'tag', 'category'); // Select specific fields for response
        }]);

        return response()->json($trip->tags); // Return only the updated trip tags
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
