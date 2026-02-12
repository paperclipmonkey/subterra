<?php

namespace App\Http\Controllers;

use App\Models\CaveSystem;
use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RouteController extends Controller
{
    public function index(CaveSystem $caveSystem)
    {
        return $caveSystem->routes()->with(['entrance', 'exit', 'tackle', 'media', 'tags'])->get();
    }

    public function show(Route $route)
    {
        return $route->load(['entrance', 'exit', 'tackle', 'media', 'tags', 'caveSystem']);
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
            'hero_image' => 'nullable|string',
            'tackle' => 'array',
            'tackle.*.description' => 'required|string',
            'tackle.*.type' => 'required|string',
            'tackle.*.length' => 'nullable|integer',
            'tackle.*.optional' => 'boolean',
            'tackle.*.quantity' => 'integer',
            'media' => 'array',
            'media.*.data' => 'nullable|string',
            'media.*.caption' => 'nullable|string',
            'media.*.type' => 'nullable|string',
        ]);

        if (isset($validated['hero_image'])) {
            $validated['hero_image'] = $this->handleImageUpload($validated['hero_image'], 'route_hero');
        }

        return DB::transaction(function () use ($validated, $caveSystem, $request) {
            $route = $caveSystem->routes()->create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']).'-'.Str::random(6),
                'description' => $validated['description'] ?? null,
                'entrance_id' => $validated['entrance_id'] ?? null,
                'exit_id' => $validated['exit_id'] ?? null,
                'duration' => $validated['duration'] ?? null,
                'grade' => $validated['grade'] ?? null,
                'hero_image' => $validated['hero_image'] ?? null,
            ]);

            if (!empty($validated['tackle'])) {
                foreach ($validated['tackle'] as $tackleData) {
                    $route->tackle()->create($tackleData);
                }
            }

            if (!empty($validated['media'])) {
                foreach ($validated['media'] as $mediaData) {
                    if (isset($mediaData['data'])) {
                        $path = $this->handleImageUpload($mediaData['data'], 'route_media');
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
            'hero_image' => 'nullable|string',
            'tackle' => 'array',
            'media' => 'array',
            'deleted_media' => 'array',
            'deleted_media.*' => 'integer',
        ]);

        if (isset($validated['hero_image']) && $validated['hero_image'] !== $route->hero_image) {
            $validated['hero_image'] = $this->handleImageUpload($validated['hero_image'], 'route_hero');
        } else {
            unset($validated['hero_image']);
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
                foreach ($validated['media'] as $mediaData) {
                    if (isset($mediaData['data'])) {
                        $path = $this->handleImageUpload($mediaData['data'], 'route_media');
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

    private function handleImageUpload($imageData, $prefix = 'image')
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
            $data = substr($imageData, strpos($imageData, ',') + 1);
            $type = strtolower($type[1]);

            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp'])) {
                return;
            }

            $data = base64_decode($data);
            if ($data === false) {
                return;
            }

            $filename = $prefix.'_'.Str::random(10).'.'.$type;
            Storage::disk('public')->put('routes/'.$filename, $data);

            return Storage::url('routes/'.$filename);
        }

        if (preg_match('/^data:application\/pdf;base64,/', $imageData)) {
            $data = substr($imageData, strpos($imageData, ',') + 1);
            $data = base64_decode($data);
            if ($data === false) {
                return;
            }

            $filename = $prefix.'_'.Str::random(10).'.pdf';
            Storage::disk('public')->put('routes/'.$filename, $data);

            return Storage::url('routes/'.$filename);
        }

        return $imageData;
    }

    public function destroy(Route $route)
    {
        $route->delete();

        return response()->noContent();
    }
}
