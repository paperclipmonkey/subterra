<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CaveSystem;
use App\Models\Route;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RouteController extends Controller
{
    public function index(Request $request, CaveSystem $caveSystem)
    {
        $routes = $caveSystem->routes()->with(['entrance', 'exit', 'tackle', 'media', 'tags'])->get();
        $routes->each(fn (Route $route) => $this->gateCaves($route, $request->user()));

        return $routes;
    }

    public function show(Request $request, Route $route)
    {
        $route->load([
            'entrance', 'exit', 'tackle', 'media', 'tags',
            'caveSystem' => fn ($query) => $query->withCount('caves'),
        ]);

        return $this->gateCaves($route, $request->user());
    }

    /**
     * These routes are public, but embed raw Cave models. Apply CaveResource's
     * gates: admin_only sites (e.g. coal mines) only for data admins, and
     * coordinates / access info only for approved-club members.
     */
    private function gateCaves(Route $route, ?User $user): Route
    {
        $canManage = $user?->hasRole(['platform_admin', 'data_admin']) ?? false;
        $canSeeLocations = $canManage || (bool) $user?->hasApprovedClub();

        foreach (['entrance', 'exit'] as $relation) {
            $cave = $route->getRelation($relation);
            if ($cave === null) {
                continue;
            }
            if (!$canManage && $cave->visibility === 'admin_only') {
                $route->setRelation($relation, null);

                continue;
            }
            if (!$canSeeLocations) {
                $cave->makeHidden(['location_lat', 'location_lng', 'location_alt', 'access_info']);
            }
        }

        return $route;
    }

    public function store(Request $request, CaveSystem $caveSystem)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'entrance_id' => 'nullable|exists:caves,id',
            'exit_id' => 'nullable|exists:caves,id',
            'duration' => 'nullable|string',
            'grade' => 'nullable|integer|min:1|max:5',
            'hero_image' => 'nullable|array',
            'hero_image.data' => 'nullable|file|image|max:512000',
            'hero_image.photographer' => 'nullable|string|max:255',
            'hero_image.copyright' => 'nullable|string|max:255',
            'tackle' => 'array',
            'tackle.*.description' => 'required|string',
            'tackle.*.type' => 'required|string',
            'tackle.*.length' => 'nullable|integer',
            'tackle.*.optional' => 'boolean',
            'tackle.*.quantity' => 'integer',
            'media' => 'array',
            'media.*.data' => 'nullable|file',
            'media.*.caption' => 'nullable|string',
            'media.*.type' => 'nullable|string',
        ]);

        $heroImagePath = null;
        if ($heroFile = $request->file('hero_image.data')) {
            $heroImagePath = $this->handleImageUpload($heroFile, 'route_hero');
        }

        return DB::transaction(function () use ($validated, $caveSystem, $request, $heroImagePath) {
            $route = $caveSystem->routes()->create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']).'-'.Str::random(6),
                'description' => $validated['description'] ?? null,
                'entrance_id' => $validated['entrance_id'] ?? null,
                'exit_id' => $validated['exit_id'] ?? null,
                'duration' => $validated['duration'] ?? null,
                'grade' => $validated['grade'] ?? null,
                'hero_image' => $heroImagePath,
                'hero_image_photographer' => $validated['hero_image']['photographer'] ?? null,
                'hero_image_copyright' => $validated['hero_image']['copyright'] ?? null,
            ]);

            if (!empty($validated['tackle'])) {
                foreach ($validated['tackle'] as $tackleData) {
                    $route->tackle()->create($tackleData);
                }
            }

            if (!empty($validated['media'])) {
                foreach ($validated['media'] as $index => $mediaData) {
                    $mediaFile = $request->file("media.{$index}.data");
                    if ($mediaFile) {
                        $path = $this->handleImageUpload($mediaFile, 'route_media');
                        if ($path) {
                            $route->media()->create([
                                'path' => $path,
                                'caption' => $mediaData['caption'] ?? null,
                                'type' => $mediaData['type'] ?? 'photo',
                            ]);
                        }
                    }
                }
            }

            return $route->load(['tackle', 'media']);
        });
    }

    public function update(Request $request, Route $route)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'entrance_id' => 'nullable|exists:caves,id',
            'exit_id' => 'nullable|exists:caves,id',
            'duration' => 'nullable|string',
            'grade' => 'nullable|integer|min:1|max:5',
            'hero_image' => 'nullable|array',
            'hero_image.data' => 'nullable|file|image|max:512000',
            'hero_image.photographer' => 'nullable|string|max:255',
            'hero_image.copyright' => 'nullable|string|max:255',
            'tackle' => 'array',
            'tackle.*.description' => 'required|string',
            'tackle.*.type' => 'required|string',
            'tackle.*.length' => 'nullable|integer',
            'tackle.*.optional' => 'boolean',
            'tackle.*.quantity' => 'integer',
            'media' => 'array',
            'media.*.data' => 'nullable|file',
            'media.*.caption' => 'nullable|string',
            'media.*.type' => 'nullable|string',
            'deleted_media' => 'array',
            'deleted_media.*' => 'integer',
        ]);

        // The hero image is handled manually so we never mass-assign the array.
        unset($validated['hero_image']);

        if ($request->has('hero_image')) {
            if (($heroFile = $request->file('hero_image.data')) && $heroFile->isValid()) {
                $route->hero_image = $this->handleImageUpload($heroFile, 'route_hero');
            }
            $route->hero_image_photographer = $request->input('hero_image.photographer');
            $route->hero_image_copyright = $request->input('hero_image.copyright');
        }

        return DB::transaction(function () use ($validated, $route, $request) {
            $route->update($validated);

            if (isset($validated['tackle'])) {
                $route->tackle()->delete();
                foreach ($validated['tackle'] as $tackleData) {
                    $route->tackle()->create($tackleData);
                }
            }

            if (isset($validated['deleted_media'])) {
                $route->media()->whereIn('id', $validated['deleted_media'])->delete();
            }

            if (isset($validated['media'])) {
                // Append new media items
                foreach ($validated['media'] as $index => $mediaData) {
                    $mediaFile = $request->file("media.{$index}.data");
                    if ($mediaFile) {
                        $path = $this->handleImageUpload($mediaFile, 'route_media');
                        if ($path) {
                            $route->media()->create([
                                'path' => $path,
                                'caption' => $mediaData['caption'] ?? null,
                                'type' => $mediaData['type'] ?? 'photo',
                            ]);
                        }
                    }
                }
            }

            return $route->load(['entrance', 'exit', 'tackle', 'media', 'tags', 'caveSystem']);
        });
    }

    private function handleImageUpload($file, $prefix = 'image')
    {
        if (!$file instanceof \Illuminate\Http\UploadedFile) {
            return;
        }

        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension();
        $filename = "{$prefix}_".Str::random(10).".{$extension}";
        $path = "routes/{$filename}";

        Storage::disk('media')->putFileAs('routes', $file, $filename);

        return $path;
    }

    public function destroy(Route $route)
    {
        $route->delete();

        return response()->noContent();
    }
}
