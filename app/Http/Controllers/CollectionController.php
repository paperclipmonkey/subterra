<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Cave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CollectionController extends Controller
{
    public function index()
    {
        return Collection::withCount('caves')->get();
    }

    public function show(Collection $collection)
    {
        // Calculate progress for the current user
        $user = Auth::user();
        
        $collection->load(['caves' => function ($query) use ($user) {
            // Check if the user has visited this cave (entrance or exit in a trip)
            $query->withExists(['entranceTrips as is_entrance' => function ($q) use ($user) {
                $q->whereHas('participants', function ($u) use ($user) {
                    $u->where('users.id', $user->id);
                });
            }])->withExists(['exitTrips as is_exit' => function ($q) use ($user) {
                 $q->whereHas('participants', function ($u) use ($user) {
                    $u->where('users.id', $user->id);
                });
            }]);
        }]);

        // Transform collection to standard "is_ticked"
        // Since we can't easily do "OR" in withExists in one go, we load both and merge in PHP.
        // It's a small performance cost for better accuracy.
        // Note: We are returning the model, so we can append an attribute or just let frontend handle it.
        // But the frontend expects `is_ticked`.
        $collection->caves->each(function($cave) {
            $cave->is_ticked = $cave->is_entrance || $cave->is_exit;
            unset($cave->is_entrance, $cave->is_exit);
        });

        return $collection;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'photo_path' => 'nullable|string',
            'is_official' => 'boolean',
        ]);

        $validated['user_id'] = Auth::id(); // Assign to current user

        $collection = Collection::create($validated);

        return response()->json($collection, 201);
    }

    public function update(Request $request, Collection $collection)
    {
        // Authorization: Only admin or owner
        if ($request->user()->id !== $collection->user_id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'photo_path' => 'nullable|string',
            'is_official' => 'boolean',
        ]);

        $collection->update($validated);

        return response()->json($collection);
    }

    public function destroy(Request $request, Collection $collection)
    {
         if ($request->user()->id !== $collection->user_id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $collection->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function addCave(Request $request, Collection $collection)
    {
        if ($request->user()->id !== $collection->user_id && !$request->user()->is_admin) {
             return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'cave_id' => 'required|exists:caves,id',
        ]);

        $collection->caves()->syncWithoutDetaching([$request->cave_id]);

        return response()->json(['message' => 'Cave added']);
    }

    public function removeCave(Request $request, Collection $collection, Cave $cave)
    {
        if ($request->user()->id !== $collection->user_id && !$request->user()->is_admin) {
             return response()->json(['message' => 'Unauthorized'], 403);
        }

        $collection->caves()->detach($cave->id);

        return response()->json(['message' => 'Cave removed']);
    }
}
