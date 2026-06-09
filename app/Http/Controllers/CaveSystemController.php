<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaveSystemRequest;
use App\Http\Requests\UpdateCaveSystemRequest;
use App\Http\Resources\CaveResource;
use App\Http\Resources\CaveSystemResource;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use App\Services\ImageProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CaveSystemController extends Controller
{
    public function __construct(
        private readonly ImageProcessingService $imageProcessingService
    ) {
    }

    public function index()
    {
        return CaveSystemResource::collection(
            CaveSystem::with(['caves.tags', 'tags', 'files'])->orderBy('name')->get()
        );
    }

    public function store(StoreCaveSystemRequest $request)
    {
        $validated = $request->validated();
        $caveSystem = CaveSystem::create($validated);

        return response()->json(new CaveSystemResource($caveSystem), 201);
    }

    public function show(CaveSystem $caveSystem)
    {
        $caveSystem->load(['files', 'caves.tags', 'tags', 'annotation']);

        return new CaveSystemResource($caveSystem);
    }

    public function update(UpdateCaveSystemRequest $request, CaveSystem $caveSystem)
    {
        $caveSystem->update($request->validated());

        // Handle file deletions first
        if ($request->filled('deleted_files') && is_array($request->input('deleted_files'))) {
            $filesToDelete = $caveSystem->files()->whereIn('id', $request->input('deleted_files'))->get();
            foreach ($filesToDelete as $fileToDelete) {
                Storage::disk('media')->delete("cave_system_files/{$caveSystem->id}/{$fileToDelete->filename}");
                if ($fileToDelete->thumbnail_filename) {
                    Storage::disk('media')->delete("cave_system_files/{$caveSystem->id}/{$fileToDelete->thumbnail_filename}");
                }
                $fileToDelete->delete();
            }
        }

        // Handle file updates
        if ($request->filled('updated_files') && is_array($request->input('updated_files'))) {
            foreach ($request->input('updated_files') as $fileData) {
                if (isset($fileData['id'])) {
                    $file = $caveSystem->files()->find($fileData['id']);
                    if ($file) {
                        $file->update([
                            'original_filename' => $fileData['original_filename'] ?? $file->original_filename,
                            'details' => $fileData['details'] ?? $file->details,
                        ]);
                    }
                }
            }
        }

        // Handle new file uploads
        if ($request->hasFile('new_files')) {
            // Allowed MIME types for security
            $allowedMimes = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
            ];

            $details = $request->input('new_file_details', []);

            foreach ($request->file('new_files') as $index => $file) {
                if ($file->isValid()) {
                    // SERVER-SIDE MIME validation (don't trust client)
                    $mimeType = $file->getMimeType();

                    if (!in_array($mimeType, $allowedMimes)) {
                        throw ValidationException::withMessages([
                            'new_files.'.$index => 'File type not allowed. Only PDF and image files are permitted.',
                        ]);
                    }

                    // Use hash-based naming to prevent path traversal and collisions
                    $extension = $file->extension();
                    $filename = hash('sha256', $file->getClientOriginalName().time().$index).'.'.$extension;
                    $path = "cave_system_files/{$caveSystem->id}";

                    // Save the file to the 'media' disk
                    $filePath = $file->storeAs($path, $filename, ['disk' => 'media']);

                    // Get details for this file, ensuring index exists
                    $fileDetails = $details[$index] ?? null;

                    // Create database record with server-detected MIME type
                    $fileRecord = $caveSystem->files()->create([
                        'filename' => $filename,
                        'details' => $fileDetails,
                        'original_filename' => $file->getClientOriginalName(),
                        'mime_type' => $mimeType,
                        'size' => $file->getSize(),
                        // 'thumbnail_filename' => $thumbnailFilename // Will be set by job
                    ]);

                    // Dispatch job to generate thumbnail
                    \App\Jobs\GenerateCaveSystemThumbnail::dispatch($fileRecord);
                }
            }
        }

        $caveSystem->load('files');

        return new CaveSystemResource($caveSystem);
    }

    /**
     * Create a new cave system and its first cave in one request.
     */
    public function storeWithCave(Request $request)
    {
        $request->validate([
            'system.name' => 'required|string|max:255',
            'system.length' => 'required|integer',
            'system.vertical_range' => 'required|integer',
            'system.description' => 'nullable|string',
            'system.slug' => 'nullable|string|max:255',
            'system.references' => 'nullable|string',
            'system.catchment_id' => 'nullable|exists:catchments,id',
            'cave.name' => 'required|string|max:255',
            'cave.description' => 'nullable|string',
            'cave.location_name' => 'required|string|max:255',
            'cave.location_country' => 'required|string|max:255',
            'cave.location_lat' => 'required|numeric',
            'cave.location_lng' => 'required|numeric',
            'cave.location_alt' => 'nullable|numeric',
            'cave.access_info' => 'nullable|string',
            'cave.slug' => 'nullable|string|max:255',
        ]);

        $systemData = $request->input('system');
        $caveSystem = CaveSystem::create($systemData);

        $caveData = $request->input('cave');
        $caveData['cave_system_id'] = $caveSystem->id;

        $imageData = [
            'hero_image' => $caveData['hero_image'] ?? null,
            'entrance_image' => $caveData['entrance_image'] ?? null,
        ];
        $tagsData = $caveData['tags'] ?? [];

        unset($caveData['hero_image'], $caveData['entrance_image'], $caveData['tags']);

        $cave = Cave::create($caveData);

        // Process Images
        foreach (['hero_image', 'entrance_image'] as $field) {
            $fileData = $request->file("cave.{$field}.data");

            if ($fileData || (!empty($imageData[$field]) && is_array($imageData[$field]))) {
                $type = str_replace('_image', '', $field); // 'hero' or 'entrance'
                $data = $imageData[$field] ?? [];

                // Override string data with binary file stream
                if ($fileData) {
                    $data['data'] = $fileData;
                }

                // Check if data key exists and is valid
                if (!empty($data['data'])) {
                    $filePath = $this->imageProcessingService->processAndStoreImage($data, 'caves', $type);

                    $cave->media()->create([
                        'type' => $type,
                        'filename' => $filePath,
                        'title' => $data['title'] ?? null,
                        'photographer' => $data['photographer'] ?? null,
                        'copyright' => $data['copyright'] ?? null,
                    ]);
                }
            }
        }

        if (!empty($tagsData)) {
            $tags = collect($tagsData)->map(function ($tag) {
                return Tag::where([
                    'category' => $tag['category'],
                    'tag' => $tag['tag'],
                    'assignable' => true,
                ])->first()?->id;
            })->filter();
            $cave->tags()->sync($tags);
        }

        $caveSystem->load('caves');

        $cave->refresh();
        $cave->load('tags');

        return response()->json([
            'system' => new CaveSystemResource($caveSystem),
            'cave' => new CaveResource($cave),
        ], 201);
    }
}
