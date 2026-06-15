<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CaveSystemMapOverlayResource;
use App\Models\CaveSystem;
use App\Models\CaveSystemMapOverlay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CaveSystemMapOverlayController extends Controller
{
    /**
     * Accepted file extensions for GeoTIFF uploads.
     *
     * @var array<int, string>
     */
    private array $allowedExtensions = ['tif', 'tiff', 'gtiff', 'geotiff'];

    public function index(CaveSystem $caveSystem)
    {
        $overlays = $caveSystem->mapOverlays()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        return CaveSystemMapOverlayResource::collection($overlays);
    }

    public function store(Request $request, CaveSystem $caveSystem)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // GeoTIFFs are large; allow up to 100MB. Extension is checked below
            // because GeoTIFFs report inconsistent MIME types (image/tiff vs
            // application/octet-stream) across browsers and operating systems.
            'file' => 'required|file|max:102400',
            'bounds' => 'nullable|array|size:4',
            'bounds.*' => 'numeric',
            'opacity' => 'nullable|numeric|min:0|max:1',
            'visible_by_default' => 'nullable|boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());

        if (!in_array($extension, $this->allowedExtensions, true)) {
            return response()->json([
                'message' => 'The uploaded file must be a GeoTIFF (.tif or .tiff).',
                'errors' => ['file' => ['The uploaded file must be a GeoTIFF (.tif or .tiff).']],
            ], 422);
        }

        $filename = hash('sha256', $file->getClientOriginalName().microtime(true)).'.'.$extension;
        $path = "cave_system_overlays/{$caveSystem->id}";
        $file->storeAs($path, $filename, ['disk' => 'media']);

        $overlay = $caveSystem->mapOverlays()->create([
            'name' => $validated['name'],
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'bounds' => isset($validated['bounds']) ? array_map('floatval', $validated['bounds']) : null,
            'opacity' => $validated['opacity'] ?? 0.8,
            'visible_by_default' => $request->boolean('visible_by_default', true),
            'display_order' => $validated['display_order'] ?? 0,
        ]);

        return (new CaveSystemMapOverlayResource($overlay))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, CaveSystem $caveSystem, CaveSystemMapOverlay $overlay)
    {
        abort_unless($overlay->cave_system_id === $caveSystem->id, 404);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'opacity' => 'sometimes|numeric|min:0|max:1',
            'visible_by_default' => 'sometimes|boolean',
            'display_order' => 'sometimes|integer|min:0',
        ]);

        $overlay->update($validated);

        return new CaveSystemMapOverlayResource($overlay);
    }

    public function destroy(CaveSystem $caveSystem, CaveSystemMapOverlay $overlay)
    {
        abort_unless($overlay->cave_system_id === $caveSystem->id, 404);

        Storage::disk('media')->delete("cave_system_overlays/{$caveSystem->id}/{$overlay->filename}");
        $overlay->delete();

        return response()->json(null, 204);
    }
}
