<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cave;
use App\Models\CaveSystem;
use Illuminate\Http\JsonResponse;

class TaskController extends Controller
{
    /**
     * Get list of pending admin tasks.
     */
    public function index(): JsonResponse
    {
        // 1. Caves without Photos (missing both hero and entrance)
        $cavesNoPhoto = Cave::whereDoesntHave('media', function ($query) {
            $query->whereIn('type', ['hero', 'entrance']);
        })
            ->select('id', 'name', 'slug', 'location_name')
            ->orderBy('name')
            ->get();

        // 2. Caves without Description
        $cavesNoDesc = Cave::where(function ($query) {
            $query->whereNull('description')
                  ->orWhere('description', '');
        })
            ->select('id', 'name', 'slug', 'location_name')
            ->orderBy('name')
            ->get();

        // 3. Caves with Low Tags (< 3)
        $cavesLowTags = Cave::withCount('tags')
            ->has('tags', '<', 3)
            ->select('id', 'name', 'slug', 'location_name')
            ->orderBy('name')
            ->get();

        // 4. Systems without References
        $systemsNoRefs = CaveSystem::where(function ($query) {
            $query->whereNull('references')
                  ->orWhere('references', '');
        })
            ->select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        // 5. Systems without Surveys (Files)
        $systemsNoFiles = CaveSystem::doesntHave('files')
            ->select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        return response()->json([
            'caves_no_photo' => $cavesNoPhoto,
            'caves_no_description' => $cavesNoDesc,
            'caves_low_tags' => $cavesLowTags,
            'systems_no_references' => $systemsNoRefs,
            'systems_no_files' => $systemsNoFiles,
        ]);
    }
}
