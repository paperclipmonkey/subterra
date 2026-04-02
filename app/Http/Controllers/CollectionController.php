<?php

namespace App\Http\Controllers;

use App\Http\Resources\CollectionResource;
use App\Models\Cave;
use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CollectionController extends Controller
{
    public function __construct(
        private readonly \App\Services\ImageProcessingService $imageProcessingService
    ) {
    }

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
            $query->with(['heroImage', 'entranceImage', 'tags', 'media', 'system'])
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
            'photo' => 'nullable',
            'photo_data' => 'nullable|string',
            'caves' => 'nullable|array',
            'caves.*.id' => 'required|exists:caves,id',
            'caves.*.description' => 'nullable|string',
        ]);

        $validated['user_id'] = Auth::id();

        if ($photoPath = $this->processPhotoField($request)) {
            $validated['photo_path'] = $photoPath;
        }

        unset($validated['photo'], $validated['photo_data']);
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
            'photo' => 'nullable',
            'photo_data' => 'nullable|string',
            'caves' => 'nullable|array',
            'caves.*.id' => 'required|exists:caves,id',
            'caves.*.description' => 'nullable|string',
        ]);

        if ($photoPath = $this->processPhotoField($request)) {
            $validated['photo_path'] = $photoPath;
        }

        unset($validated['photo'], $validated['photo_data']);
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

    private function processPhotoField(Request $request): ?string
    {
        // 1. Check for multipart file upload
        if ($request->hasFile('photo')) {
            return $this->imageProcessingService->processAndStoreImage(
                ['data' => $request->file('photo')],
                'collections'
            );
        }

        // 2. Check for base64 in photo_data or photo_path
        $base64 = $request->input('photo_data') ?? $request->input('photo_path');
        if (is_string($base64) && str_starts_with($base64, 'data:image')) {
            return $this->imageProcessingService->processAndStoreBase64Image($base64, 'collections');
        }

        return null;
    }
}
