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
                Storage::disk('public')->delete("cave_system_files/{$caveSystem->id}/{$fileToDelete->filename}");
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
                    $storedPath = $file->storeAs($path, $filename, 'public');

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

    // public function destroy(Cave $cave)
    // {
    //     Cave::destroy($cave);
    // }
}
