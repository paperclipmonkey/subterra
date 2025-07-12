<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaveRequest;
use App\Http\Requests\UpdateCaveRequest;
use App\Http\Resources\CaveResource;
use App\Models\Cave;
use App\Services\ImageProcessingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CaveController extends Controller
{
    public function __construct(
        private readonly ImageProcessingService $imageProcessingService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return CaveResource::collection(Cave::orderBy('name')->get());
    }

    public function store(StoreCaveRequest $request): Response
    {
        Cave::create($request->validated());

        return response()->noContent();
    }

    public function show(Cave $cave): CaveResource
    {
        return new CaveResource($cave);
    }

    public function update(UpdateCaveRequest $request, Cave $cave): CaveResource
    {
        $cave->update($request->validated());

        // Update tags
        $tags = collect($request->input('tags', []))->map(function ($tag) {
            return \App\Models\Tag::where([
                'category' => $tag['category'],
                'tag' => $tag['tag'],
                'assignable' => true
            ])->first()?->id;
        })->filter();
        $cave->tags()->sync($tags);

        // Process hero image
        $this->processImageField($request, $cave, 'hero_image');

        // Process entrance image
        $this->processImageField($request, $cave, 'entrance_image');

        return new CaveResource($cave);
    }

    private function processImageField(UpdateCaveRequest $request, Cave $cave, string $fieldName): void
    {
        if ($request->has($fieldName) && $request->input($fieldName) !== null) {
            $imageData = $request->input($fieldName);
            if (is_array($imageData)) {
                $suffix = str_replace('_image', '', $fieldName);
                $filePath = $this->imageProcessingService->processAndStoreImage($imageData, 'caves', $suffix);
                $cave->update([$fieldName => $filePath]);
            }
        } else {
            $cave->update([$fieldName => null]);
        }
    }
}