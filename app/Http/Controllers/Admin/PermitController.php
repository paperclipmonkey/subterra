<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermitResource;
use App\Jobs\ProcessImageCloudJob;
use App\Models\Permit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PermitController extends Controller
{
    use AuthorizesRequests;

    public function index(): ResourceCollection
    {
        $permits = Permit::with(['caves', 'officers'])
            ->withCount('bookings')
            ->orderBy('name')
            ->get();

        return PermitResource::collection($permits);
    }

    public function show(Permit $permit): PermitResource
    {
        $permit->load(['caves', 'officers', 'bookings' => function ($q) {
            $q->where('status', 'approved')
              ->where('date', '>=', now()->toDateString())
              ->orderBy('date');
        }, 'bookings.applicant']);

        return new PermitResource($permit);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:permits,slug',
            'description' => 'nullable|string',
            'photo_photographer' => 'nullable|string|max:255',
            'photo_copyright' => 'nullable|string|max:255',
            'conditions' => 'nullable|string',
            'has_max_groups_per_day' => 'boolean',
            'max_groups_per_day' => 'nullable|integer|min:1',
            'has_max_participants' => 'boolean',
            'max_participants' => 'nullable|integer|min:1',
            'has_season' => 'boolean',
            'season_start' => 'nullable|string|regex:/^\d{2}-\d{2}$/',
            'season_end' => 'nullable|string|regex:/^\d{2}-\d{2}$/',
            'auto_approve' => 'boolean',
            'booking_info' => 'nullable|string',
            'is_active' => 'boolean',
            'cave_ids' => 'nullable|array',
            'cave_ids.*' => 'exists:caves,id',
            'officer_ids' => 'nullable|array',
            'officer_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['created_by'] = $request->user()->id;

        $permit = Permit::create($data);

        if (!empty($data['cave_ids'])) {
            $permit->caves()->sync($data['cave_ids']);
        }

        if (!empty($data['officer_ids'])) {
            $permit->officers()->sync($data['officer_ids']);
        }

        $permit->load(['caves', 'officers']);

        return response()->json(new PermitResource($permit), 201);
    }

    public function update(Request $request, Permit $permit): JsonResponse
    {
        $this->authorize('update', $permit);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:permits,slug,'.$permit->id,
            'description' => 'nullable|string',
            'photo_photographer' => 'nullable|string|max:255',
            'photo_copyright' => 'nullable|string|max:255',
            'conditions' => 'nullable|string',
            'has_max_groups_per_day' => 'boolean',
            'max_groups_per_day' => 'nullable|integer|min:1',
            'has_max_participants' => 'boolean',
            'max_participants' => 'nullable|integer|min:1',
            'has_season' => 'boolean',
            'season_start' => 'nullable|string|regex:/^\d{2}-\d{2}$/',
            'season_end' => 'nullable|string|regex:/^\d{2}-\d{2}$/',
            'auto_approve' => 'boolean',
            'booking_info' => 'nullable|string',
            'is_active' => 'boolean',
            'cave_ids' => 'nullable|array',
            'cave_ids.*' => 'exists:caves,id',
            'officer_ids' => 'nullable|array',
            'officer_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();
        $permit->update($data);

        if (array_key_exists('cave_ids', $data)) {
            $permit->caves()->sync($data['cave_ids'] ?? []);
        }

        if (array_key_exists('officer_ids', $data)) {
            $permit->officers()->sync($data['officer_ids'] ?? []);
        }

        $permit->load(['caves', 'officers']);

        return response()->json(new PermitResource($permit));
    }

    public function uploadPhoto(Request $request, Permit $permit): JsonResponse
    {
        $this->authorize('update', $permit);

        $validator = Validator::make($request->all(), [
            'photo' => ['required', 'file', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Remove the previous photo (and its preserved original) if they live on
        // our disk rather than being an external URL.
        foreach ([$permit->photo_path, $permit->original_filename] as $old) {
            if ($old && !str_starts_with($old, 'http')) {
                Storage::disk('media')->delete($old);
            }
        }

        $path = $request->file('photo')->store('permits', 'media');
        $permit->photo_path = $path;
        // Cleared so the webhook records this upload as the new preserved original.
        $permit->original_filename = null;
        $permit->save();

        // Convert to responsive WebP variants in the background, same as trip
        // photos. The webhook swaps photo_path to the processed variant on success.
        ProcessImageCloudJob::dispatch($path, Permit::class, $permit->id);

        $permit->load(['caves', 'officers']);

        return response()->json(new PermitResource($permit));
    }

    public function deletePhoto(Permit $permit): JsonResponse
    {
        $this->authorize('update', $permit);

        foreach ([$permit->photo_path, $permit->original_filename] as $old) {
            if ($old && !str_starts_with($old, 'http')) {
                Storage::disk('media')->delete($old);
            }
        }

        $permit->photo_path = null;
        $permit->original_filename = null;
        $permit->save();

        $permit->load(['caves', 'officers']);

        return response()->json(new PermitResource($permit));
    }

    public function destroy(Permit $permit): JsonResponse
    {
        $this->authorize('delete', $permit);

        $permit->delete();

        return response()->json(null, 204);
    }
}
