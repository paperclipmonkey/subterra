<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaveSystemRequest;
use App\Http\Requests\UpdateCaveSystemRequest;
use App\Http\Resources\CaveSystemResource;
use App\Models\Cave;
use App\Models\CaveSystem;
use Illuminate\Http\Request; // Use base request or your FormRequest
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse; // Import JsonResponse

class CaveSystemController extends Controller
{
    public function index()
    {
        return CaveSystemResource::collection(CaveSystem::orderBy('name')->get());
    }

    public function store(StoreCaveSystemRequest $request)
    {
        CaveSystem::create($request->all())->save();
    }

    public function show(CaveSystem $caveSystem)
    {
        $caveSystem->load('files'); // Eager load files
        return new CaveSystemResource($caveSystem);
    }

    public function update(Request $request, CaveSystem $caveSystem): JsonResponse // Type hint return
    {
        $caveSystem->update($request->except(['new_files', 'new_file_details', 'deleted_files'])); // Example

        // Handle file deletions first
        if ($request->filled('deleted_files') && is_array($request->input('deleted_files'))) {
            $filesToDelete = $caveSystem->files()->whereIn('id', $request->input('deleted_files'))->get();
            foreach ($filesToDelete as $fileToDelete) {
                // Delete file from storage (adjust disk and path if needed)
                Storage::disk('media')->delete("cave_system_files/{$caveSystem->id}/{$fileToDelete->filename}");
                // Delete record from database
                $fileToDelete->delete();
            }
        }

        // Handle new file uploads
        if ($request->hasFile('new_files')) {
            // Ensure details array exists and matches file count if provided
            $details = $request->input('new_file_details', []);

            foreach ($request->file('new_files') as $index => $file) {
                if ($file->isValid()) {
                    // Generate a unique filename (e.g., using hashName or UUID)
                    // $filename = $file->hashName();
                    $filename = uniqid() . '_' . $file->getClientOriginalName(); // Or use hashName()
                    // Define storage path (adjust disk and path if needed)
                    $path = "cave_system_files/{$caveSystem->id}";

                    // Store the file
                    $storedPath = $file->storeAs($path, $filename, 'media');

                    // Get details for this file, ensuring index exists
                    $fileDetails = $details[$index] ?? null;

                    // Create database record
                    $caveSystem->files()->create([
                        'filename'          => $filename,
                        'details'           => $fileDetails,
                        'original_filename' => $file->getClientOriginalName(),
                        'mime_type'         => $file->getClientMimeType(),
                        'size'              => $file->getSize(),
                    ]);
                }
                // Optional: Add error handling for invalid files
            }
        }

        // Load the updated relations if necessary before returning
        $caveSystem->load('files');

        // Return the updated resource as JSON
        return response()->json(new CaveSystemResource($caveSystem));
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
        $caveSystem = \App\Models\CaveSystem::create($systemData);

        $caveData = $request->input('cave');
        $caveData['cave_system_id'] = $caveSystem->id;
        $cave = \App\Models\Cave::create($caveData);

        $caveSystem->load('caves');
        return response()->json([
            'system' => new \App\Http\Resources\CaveSystemResource($caveSystem),
            'cave' => new \App\Http\Resources\CaveResource($cave),
        ], 201);
    }

    // public function destroy(Cave $cave)
    // {
    //     Cave::destroy($cave);
    // }
}
