<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CollectionResource;
use App\Models\Cave;
use App\Models\Collection;
use App\Models\User;
use App\Policies\CavePolicy;
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
            // A collection may predate a cave being made admin_only (e.g. a coal
            // mine), so such sites must also be filtered here, when it is read.
            $this->hideRestrictedCaves($query, $user);

            // Check if the user has visited this cave (entrance or exit in a trip)
            $query->with(['heroImage', 'entranceImage', 'heroVideo', 'tags', 'media', 'system'])
                ->withExists(['trips as has_done' => function ($q) use ($user) {
                    $q->whereHas('participants', function ($u) use ($user) {
                        $u->where('users.id', $user->id);
                    });
                }])
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
            'caves.*.id' => ['required', 'exists:caves,id', $this->visibleCave($request)],
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

        // Reload caves with pivot data for consistent response, filtered as in show().
        $collection->load(['caves' => function ($query) use ($request) {
            $this->hideRestrictedCaves($query, $request->user());
            $query->orderByPivot('sort_order');
        }]);

        return new CollectionResource($collection);
    }

    public function update(Request $request, Collection $collection)
    {
        // Authorization: only the owner or a platform admin (not is_admin, which
        // is true for every staff role)
        if ($request->user()->id !== $collection->user_id && !$request->user()->hasRole('platform_admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'photo' => 'nullable',
            'photo_data' => 'nullable|string',
            'caves' => 'nullable|array',
            'caves.*.id' => ['required', 'exists:caves,id', $this->visibleCave($request)],
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

        $collection->load(['caves' => function ($query) use ($request) {
            $this->hideRestrictedCaves($query, $request->user());
            $query->orderByPivot('sort_order');
        }]);

        return new CollectionResource($collection);
    }

    public function destroy(Request $request, Collection $collection)
    {
        if ($request->user()->id !== $collection->user_id && !$request->user()->hasRole('platform_admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $collection->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function addCave(Request $request, Collection $collection)
    {
        if ($request->user()->id !== $collection->user_id && !$request->user()->hasRole('platform_admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'cave_id' => ['required', 'exists:caves,id', $this->visibleCave($request)],
        ]);

        $count = $collection->caves()->count();
        $collection->caves()->syncWithoutDetaching([$request->cave_id => ['sort_order' => $count]]);

        return response()->json(['message' => 'Cave added']);
    }

    public function removeCave(Request $request, Collection $collection, Cave $cave)
    {
        if ($request->user()->id !== $collection->user_id && !$request->user()->hasRole('platform_admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $collection->caves()->detach($cave->id);

        return response()->json(['message' => 'Cave removed']);
    }

    /**
     * admin_only sites (e.g. coal mines) pass `exists`, but the collection then
     * serialises them back through CaveResource. Reject them for anyone who may
     * not view them, with the same message as a missing id so their existence
     * is not revealed.
     */
    private function visibleCave(Request $request): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
            if (!is_scalar($value)) {
                return;
            }
            $cave = Cave::withTrashed()->find($value);
            if ($cave !== null && !app(CavePolicy::class)->view($request->user(), $cave)) {
                $fail('validation.exists')->translate();
            }
        };
    }

    /**
     * Drop admin_only caves from a caves query unless the user may manage them.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\BelongsToMany<Cave, Collection>|\Illuminate\Database\Eloquent\Builder<Cave>  $query
     */
    private function hideRestrictedCaves($query, ?User $user): void
    {
        if ($user?->hasRole(['platform_admin', 'data_admin'])) {
            return;
        }

        $query->where(fn ($q) => $q->whereNull('caves.visibility')->orWhere('caves.visibility', '!=', 'admin_only'));
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
