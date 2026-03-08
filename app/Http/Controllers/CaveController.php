<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaveRequest;
use App\Http\Requests\UpdateCaveRequest;
use App\Http\Resources\CaveResource;
use App\Http\Resources\CaveSummaryResource;
use App\Models\Cave;
use App\Models\Tag;
use App\Services\ImageProcessingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class CaveController extends Controller
{
    public function __construct(
        private readonly ImageProcessingService $imageProcessingService
    ) {
    }

    public function index(\Illuminate\Http\Request $request): AnonymousResourceCollection
    {
        if ($request->user()) {
            $request->user()->load('clubs');
        }

        $query = Cave::with(['heroImage', 'entranceImage', 'heroVideo', 'tags', 'system.tags'])
            ->orderBy('name');

        if ($request->user()) {
            $query->withExists(['trips as has_visited_system' => function ($q) use ($request) {
                $q->whereHas('participants', function ($pq) use ($request) {
                    $pq->where('users.id', $request->user()->id);
                });
            }]);
        }

        $caves = $query->get();

        return CaveSummaryResource::collection($caves);
    }

    public function store(StoreCaveRequest $request): CaveResource
    {
        $validData = $request->validated();
        if (empty($validData['slug'])) {
            $validData['slug'] = Str::slug($validData['name']);
        }

        $cave = Cave::create($validData);

        // Process tags
        if ($request->has('tags')) {
            $tags = collect($request->input('tags', []))->map(function ($tag) {
                return Tag::where([
                    'category' => $tag['category'],
                    'tag' => $tag['tag'],
                    'assignable' => true,
                ])->first()?->id;
            })->filter();
            $cave->tags()->sync($tags);
        }

        // Process hero image
        if ($request->has('hero_image')) {
            $this->processImageFieldOnCreate($request, $cave, 'hero');
        }

        // Process entrance image
        if ($request->has('entrance_image')) {
            $this->processImageFieldOnCreate($request, $cave, 'entrance');
        }

        // Process hero video
        if ($request->has('hero_video')) {
            $this->processVideoFieldOnCreate($request, $cave, 'hero_video');
        }

        return new CaveResource($cave->fresh(['media', 'heroImage', 'entranceImage', 'heroVideo']));
    }

    private function processImageFieldOnCreate(StoreCaveRequest $request, Cave $cave, string $type): void
    {
        $fieldName = $type.'_image';
        $imageData = $request->input($fieldName, []);
        $fileData = $request->file($fieldName.'.data');

        if ($fileData) {
            $imageData['data'] = $fileData;
            $filePath = $this->imageProcessingService->processAndStoreImage($imageData, 'caves', $type);

            $cave->media()->create([
                'type' => $type,
                'filename' => $filePath,
                'title' => $imageData['title'] ?? null,
                'photographer' => $imageData['photographer'] ?? null,
                'copyright' => $imageData['copyright'] ?? null,
            ]);
        }
    }

    private function processVideoFieldOnCreate(StoreCaveRequest $request, Cave $cave, string $type): void
    {
        $fieldName = $type;
        $videoData = $request->input($fieldName, []);
        $fileData = $request->file($fieldName.'.data');

        if ($fileData) {
            $videoData['data'] = $fileData;
            $filePath = $this->imageProcessingService->processAndStoreVideo($videoData, 'caves', $type);

            $cave->media()->create([
                'type' => $type,
                'filename' => $filePath,
                'title' => $videoData['title'] ?? null,
                'photographer' => $videoData['photographer'] ?? null,
                'copyright' => $videoData['copyright'] ?? null,
            ]);
        }
    }

    public function show($id)
    {
        $query = Cave::with([
            'system.catchment', 'system.caves', 'system.files', 'system.routes',
            'trips' => function ($q) {
                $q->visibleTo(auth()->user())->orderBy('start_time', 'desc')->limit(25)->with(['participants', 'media']);
            },
            'tags', 'collections', 'media', 'heroImage', 'entranceImage', 'heroVideo',
        ]);

        if (is_numeric($id)) {
            $cave = $query->where('id', $id)->first();
        }

        if (!isset($cave)) {
            $cave = $query->where('slug', $id)->firstOrFail();
        }

        return new CaveResource($cave);
    }

    public function update(UpdateCaveRequest $request, Cave $cave): CaveResource
    {
        $data = $request->validated();
        $cave->update($data);

        // Update tags
        $tags = collect($request->input('tags', []))->map(function ($tag) {
            return Tag::where([
                'category' => $tag['category'],
                'tag' => $tag['tag'],
                'assignable' => true,
            ])->first()?->id;
        })->filter();
        $cave->tags()->sync($tags);

        // Process hero image
        $this->processImageField($request, $cave, 'hero');

        // Process entrance image
        $this->processImageField($request, $cave, 'entrance');

        // Process hero video
        $this->processVideoField($request, $cave, 'hero_video');

        return new CaveResource($cave->fresh(['media', 'heroImage', 'entranceImage', 'heroVideo']));
    }

    private function processImageField(UpdateCaveRequest $request, Cave $cave, string $type): void
    {
        $fieldName = $type.'_image';

        $hasField = $request->has($fieldName);
        $imageData = $request->input($fieldName, []);
        $fileData = $request->file($fieldName.'.data');

        $isNull = !$fileData && (empty($imageData) || (isset($imageData['data']) && $imageData['data'] === null));

        if ($hasField && !$isNull) {
            if ($fileData) {
                $imageData['data'] = $fileData;
                $filePath = $this->imageProcessingService->processAndStoreImage($imageData, 'caves', $type);

                $cave->media()->updateOrCreate(
                    ['type' => $type],
                    [
                        'filename' => $filePath,
                        'title' => $imageData['title'] ?? null,
                        'photographer' => $imageData['photographer'] ?? null,
                        'copyright' => $imageData['copyright'] ?? null,
                    ]
                );
            } elseif (is_array($imageData)) {
                // Metadata update only (if file already exists or is not being replaced)
                $cave->media()->where('type', $type)->update([
                    'title' => $imageData['title'] ?? null,
                    'photographer' => $imageData['photographer'] ?? null,
                    'copyright' => $imageData['copyright'] ?? null,
                ]);
            }
        } elseif ($hasField && $isNull) {
            $cave->media()->where('type', $type)->delete();
        }
    }

    private function processVideoField(UpdateCaveRequest $request, Cave $cave, string $type): void
    {
        $fieldName = $type;

        $hasField = $request->has($fieldName);
        $videoData = $request->input($fieldName, []);
        $fileData = $request->file($fieldName.'.data');

        $isNull = !$fileData && (empty($videoData) || (isset($videoData['data']) && $videoData['data'] === null));

        if ($hasField && !$isNull) {
            if ($fileData) {
                $videoData['data'] = $fileData;
                $filePath = $this->imageProcessingService->processAndStoreVideo($videoData, 'caves', $type);

                $cave->media()->updateOrCreate(
                    ['type' => $type],
                    [
                        'filename' => $filePath,
                        'title' => $videoData['title'] ?? null,
                        'photographer' => $videoData['photographer'] ?? null,
                        'copyright' => $videoData['copyright'] ?? null,
                    ]
                );
            } elseif (is_array($videoData)) {
                // Metadata update only
                $cave->media()->where('type', $type)->update([
                    'title' => $videoData['title'] ?? null,
                    'photographer' => $videoData['photographer'] ?? null,
                    'copyright' => $videoData['copyright'] ?? null,
                ]);
            }
        } elseif ($hasField && $isNull) {
            $cave->media()->where('type', $type)->delete();
        }
    }
}
