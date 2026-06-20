<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CaveSystem;
use App\Models\CaveSystemFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The single home for a cave system's extra media & documents — surveys,
 * historic photos, reports, etc. Public files appear on the system/cave pages
 * (per the existing approved-club gate); private files and the upload/delete
 * actions are restricted to data admins.
 */
class CaveSystemFileController extends Controller
{
    /** PDF + common image types — same allow-list as the bulk system upload. */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif',
    ];

    public function index(Request $request, CaveSystem $caveSystem): JsonResponse
    {
        $user = $request->user();
        $canManage = $user && $caveSystem->managedBy($user);

        $query = $caveSystem->files()->orderBy('sort_order')->orderBy('id');
        if (!$canManage) {
            $query->where('visibility', 'public');
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request, CaveSystem $caveSystem): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$caveSystem->managedBy($user)) {
            abort(403, 'User is not authorised to add files to this cave system.');
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'max:512000'],
            'kind' => ['nullable', Rule::in(['photo', 'survey', 'document', 'historic', 'other'])],
            'visibility' => ['nullable', Rule::in(['public', 'private'])],
            'title' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'photographer' => ['nullable', 'string', 'max:255'],
            'copyright' => ['nullable', 'string', 'max:255'],
            'taken_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $file = $request->file('file');
        // Trust the server-detected MIME, not the client.
        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            abort(422, 'File type not allowed. Only PDF and image files are permitted.');
        }

        $filename = hash('sha256', $file->getClientOriginalName().microtime()).'.'.$file->extension();
        $file->storeAs("cave_system_files/{$caveSystem->id}", $filename, ['disk' => 'media']);

        $record = $caveSystem->files()->create([
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'kind' => $data['kind'] ?? 'document',
            'visibility' => $data['visibility'] ?? 'public',
            'title' => $data['title'] ?? null,
            'details' => $data['details'] ?? null,
            'photographer' => $data['photographer'] ?? null,
            'copyright' => $data['copyright'] ?? null,
            'taken_at' => $data['taken_at'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        \App\Jobs\GenerateCaveSystemThumbnail::dispatch($record);

        return response()->json(['data' => $record], 201);
    }

    public function destroy(Request $request, CaveSystem $caveSystem, CaveSystemFile $file): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$caveSystem->managedBy($user)) {
            abort(403, 'User is not authorised to remove files from this cave system.');
        }

        if ($file->cave_system_id !== $caveSystem->id) {
            abort(404);
        }

        Storage::disk('media')->delete("cave_system_files/{$caveSystem->id}/{$file->filename}");
        if ($file->thumbnail_filename) {
            Storage::disk('media')->delete("cave_system_files/{$caveSystem->id}/{$file->thumbnail_filename}");
        }
        $file->delete();

        return response()->json(null, 204);
    }
}
