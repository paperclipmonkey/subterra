<?php

namespace App\Http\Controllers;

use App\Models\SuggestedEdit;
use App\Services\MediaSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\SlackAlerts\Facades\SlackAlert;

class SuggestedEditController extends Controller
{
    public function __construct(
        private readonly MediaSuggestionService $mediaSuggestionService
    ) {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'suggestable_type' => 'required|string',
            'suggestable_id' => 'nullable', // Nullable for creation suggestions
            'suggested_data' => 'required|array',
            'original_data' => 'nullable|array',
        ]);

        $user = Auth::user();

        if (!$user->is_admin) {
            if (!$user->hasApprovedClub()) {
                abort(403, 'You must be an approved club member to suggest edits.');
            }
        }

        // Map frontend type to backend model class if needed, or rely on frontend sending full class name
        // Ideally frontend sends 'cave' and we map to App\Models\Cave
        $typeMap = [
            'cave' => \App\Models\Cave::class,
            'cave_system' => \App\Models\CaveSystem::class,
            'route' => \App\Models\Route::class,
            'collection' => \App\Models\Collection::class,
        ];

        $modelClass = $typeMap[$validated['suggestable_type']] ?? $validated['suggestable_type'];

        $suggestedData = $this->mediaSuggestionService->savePendingMedia(
            $validated['suggested_data'],
            $validated['suggestable_type']
        );

        $suggestion = SuggestedEdit::create([
            'user_id' => Auth::id(),
            'suggestable_type' => $modelClass,
            'suggestable_id' => $validated['suggestable_id'] ?? null,
            'original_data' => $validated['original_data'] ?? null,
            'suggested_data' => $suggestedData,
            'status' => 'pending',
        ]);

        // Send Slack Notification
        $entityName = $suggestedData['name'] ?? $suggestedData['title'] ?? 'Unknown';
        $typeLabel = ucfirst(str_replace('_', ' ', $validated['suggestable_type']));
        $isNew = !isset($validated['suggestable_id']) || $validated['suggestable_id'] === null;

        $message = "📝 *New Suggested Edit Submitted*\n\n".
            "*User:* {$user->name} ({$user->email})\n".
            "*Type:* {$typeLabel} ".($isNew ? '(New Item Proposal)' : '(Edit Post)')."\n".
            "*Entity:* {$entityName}\n\n".
            "*Preview:*\n".
            '> '.(isset($suggestedData['description']) ? substr(strip_tags($suggestedData['description']), 0, 150).'...' : 'No description provided')."\n\n".
            "*Review:* ".config('app.url')."/admin/suggested-edits/{$suggestion->id}";

        try {
            SlackAlert::to('corrections')->message($message);
        } catch (\Exception $e) {
            Log::error('Failed to send SuggestedEdit Slack alert: '.$e->getMessage());
        }

        return response()->json([
            'message' => 'Suggestion submitted successfully.',
            'data' => $suggestion,
        ], 201);
    }
}
