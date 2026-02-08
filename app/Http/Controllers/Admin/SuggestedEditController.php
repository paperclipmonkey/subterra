<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SuggestedEdit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\SuggestionApprovedMail;
use App\Services\MediaSuggestionService;

class SuggestedEditController extends Controller
{
    public function __construct(
        private readonly MediaSuggestionService $mediaSuggestionService
    ) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        
        return SuggestedEdit::with(['user', 'suggestable'])
            ->where('status', $status)
            ->latest()
            ->paginate(20);
    }

    public function show(SuggestedEdit $suggestedEdit)
    {
        $suggestedEdit->load(['user', 'suggestable']);

        // Eager load relationships for the suggestable model to ensure accurate diffs
        if ($suggestedEdit->suggestable) {
            $type = $suggestedEdit->suggestable_type;
            if ($type === \App\Models\Cave::class) {
                // Cave model uses 'system' relationship
                $suggestedEdit->suggestable->load(['system', 'tags']);
            } elseif ($type === \App\Models\Route::class) {
                // Route model uses 'caveSystem' relationship
                $suggestedEdit->suggestable->load(['caveSystem', 'entrance', 'exit', 'tags']);
            }
        }

        return $suggestedEdit;
    }

    public function approve(SuggestedEdit $suggestedEdit)
    {
        $targetDirMap = [
            \App\Models\Cave::class => 'caves',
            \App\Models\CaveSystem::class => 'cave_systems',
            \App\Models\Route::class => 'routes',
            \App\Models\Collection::class => 'collections',
        ];

        $targetDir = $targetDirMap[$suggestedEdit->suggestable_type] ?? 'misc';
        
        // Promote pending media to permanent storage
        $promotedData = $this->mediaSuggestionService->promotePendingMedia(
            $suggestedEdit->suggested_data, 
            $targetDir
        );
        $suggestedEdit->suggested_data = $promotedData;
        $suggestedEdit->save();

        if ($suggestedEdit->suggestable_id) {
            // Apply changes to the existing target model
            $suggestedEdit->suggestable->update($suggestedEdit->suggested_data);
        } else {
             // Create new item
             $modelClass = $suggestedEdit->suggestable_type;
             
             if (class_exists($modelClass)) {
                 $data = $suggestedEdit->suggested_data;
                 // Auto-generate slug if missing and name is present
                 if (empty($data['slug']) && !empty($data['name'])) {
                     $data['slug'] = Str::slug($data['name']);
                 }

                 $newItem = $modelClass::create($data);
                 
                 // Update the suggested edit to point to the newly created item
                 $suggestedEdit->suggestable_id = $newItem->id;
                 $suggestedEdit->save();
             }
        }

        $suggestedEdit->update(['status' => 'approved']);
        
        Mail::to($suggestedEdit->user)->send(new SuggestionApprovedMail($suggestedEdit));
        
        return response()->json(['message' => 'Suggestion approved and applied.']);
    }

    public function reject(Request $request, SuggestedEdit $suggestedEdit)
    {
        // Clean up any pending media files
        $this->mediaSuggestionService->cleanUpPendingMedia($suggestedEdit->suggested_data);

        $suggestedEdit->update([
            'status' => 'rejected',
            'admin_comment' => $request->input('admin_comment'),
        ]);
        return response()->json(['message' => 'Suggestion rejected.']);
    }
}
