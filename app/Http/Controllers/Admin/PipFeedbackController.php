<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PipFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipFeedbackController extends Controller
{
    /**
     * List flagged Pip conversations for review.
     * Defaults to thumbs-down only (the audit case).
     */
    public function index(Request $request): JsonResponse
    {
        $rating = $request->query('rating');
        $query = PipFeedback::with('user:id,name,email')
            ->orderByDesc('created_at');

        if ($rating === 'all') {
            // no filter
        } elseif ($rating === 'up') {
            $query->where('rating', '>', 0);
        } else {
            // Default: thumbs-down (the audit case)
            $query->where('rating', '<', 0);
        }

        $items = $query->limit(200)->get()->map(function (PipFeedback $f) {
            return [
                'id' => $f->id,
                'rating' => $f->rating,
                'comment' => $f->comment,
                'rated_reply' => $f->rated_reply,
                'reviewed' => $f->reviewed,
                'message_count' => is_array($f->transcript) ? count($f->transcript) : 0,
                'user' => $f->user ? [
                    'id' => $f->user->id,
                    'name' => $f->user->name,
                    'email' => $f->user->email,
                ] : null,
                'created_at' => $f->created_at,
            ];
        });

        return response()->json(['data' => $items]);
    }

    public function show(PipFeedback $feedback): JsonResponse
    {
        $feedback->load('user:id,name,email');

        return response()->json([
            'id' => $feedback->id,
            'rating' => $feedback->rating,
            'comment' => $feedback->comment,
            'transcript' => $feedback->transcript,
            'rated_reply' => $feedback->rated_reply,
            'reviewed' => $feedback->reviewed,
            'user' => $feedback->user ? [
                'id' => $feedback->user->id,
                'name' => $feedback->user->name,
                'email' => $feedback->user->email,
            ] : null,
            'created_at' => $feedback->created_at,
        ]);
    }

    public function markReviewed(PipFeedback $feedback): JsonResponse
    {
        $feedback->update(['reviewed' => !$feedback->reviewed]);

        return response()->json(['reviewed' => $feedback->reviewed]);
    }
}
