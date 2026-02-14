<?php

namespace App\Http\Controllers;

use App\Http\Resources\CollectionResource;
use App\Models\Cave;
use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CollectionController extends Controller
{
    public function index()
    {
        return CollectionResource::collection(Collection::withCount('caves')->get());
    }

    public function show(Collection $collection)
    {
        // Calculate progress for the current user
        $user = Auth::user();

        $collection->load(['caves' => function ($query) use ($user) {
            // Check if the user has visited this cave (entrance or exit in a trip)
            $query->with(['heroImage', 'entranceImage', 'tags', 'media'])
                ->withExists(['entranceTrips as is_entrance' => function ($q) use ($user) {
                    $q->whereHas('participants', function ($u) use ($user) {
                        $u->where('users.id', $user->id);
                    });
                }])->withExists(['exitTrips as is_exit' => function ($q) use ($user) {
                    $q->whereHas('participants', function ($u) use ($user) {
                        $u->where('users.id', $user->id);
                    });
                }])->orderByPivot('sort_order');
        }]);

        // Transform collection to standard "is_ticked"
        $collection->caves->each(function ($cave) {
            $cave->is_ticked = $cave->is_entrance || $cave->is_exit;
            unset($cave->is_entrance, $cave->is_exit);
        });

        return new CollectionResource($collection);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'photo' => 'nullable|image|max:10485760', // 10MB
            'caves' => 'nullable|array',
            'caves.*.id' => 'required|exists:caves,id',
            'caves.*.description' => 'nullable|string',
        ]);

        $validated['user_id'] = Auth::id();

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $this->processPhoto($request->file('photo'));
        }

        unset($validated['photo']);
        unset($validated['caves']);

        $collection = Collection::create($validated);

        if ($request->has('caves')) {
            $this->syncCaves($collection, $request->input('caves'));
        }

        // Reload caves with pivot data for consistent response
        $collection->load(['caves' => function ($query) {
            $query->orderByPivot('sort_order');
        }]);

        return new CollectionResource($collection);
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
            'photo' => 'nullable|image|max:10485760',
            'caves' => 'nullable|array',
            'caves.*.id' => 'required|exists:caves,id',
            'caves.*.description' => 'nullable|string',
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $this->processPhoto($request->file('photo'));
        }

        unset($validated['photo']);
        unset($validated['caves']);

        $collection->update($validated);

        if ($request->has('caves')) {
            $this->syncCaves($collection, $request->input('caves'));
        }

        $collection->load(['caves' => function ($query) {
            $query->orderByPivot('sort_order');
        }]);

        return new CollectionResource($collection);
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

        $count = $collection->caves()->count();
        $collection->caves()->syncWithoutDetaching([$request->cave_id => ['sort_order' => $count]]);

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

    protected function syncCaves(Collection $collection, array $caves)
    {
        $syncData = [];
        foreach ($caves as $index => $caveData) {
            $id = is_array($caveData) ? $caveData['id'] : $caveData;
            $description = is_array($caveData) ? ($caveData['description'] ?? null) : null;

            $syncData[$id] = [
                'description' => $description,
                'sort_order' => $index,
            ];
        }
        $collection->caves()->sync($syncData);
    }

    private function processPhoto(\Illuminate\Http\UploadedFile $photo): string
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $mimeType = $photo->getMimeType();

        if (!in_array($mimeType, $allowedMimes)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'photo' => 'Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed.',
            ]);
        }

        $extension = $photo->extension();
        $filename = hash('sha256', 'collection_'.time().Auth::id()).'.'.$extension;

        return $photo->storeAs('collections', $filename, 'media');
    }
}
