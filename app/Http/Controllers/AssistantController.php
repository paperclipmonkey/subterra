<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssistantChatRequest;
use App\Http\Requests\AssistantFeedbackRequest;
use App\Models\PipFeedback;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssistantController extends Controller
{
    public function __construct(
        private readonly AssistantService $assistantService,
    ) {
    }

    /**
     * Handle an AI assistant chat turn.
     *
     * Streams Server-Sent Events while the model dispatches tools, then emits the final
     * content event when the response is ready.
     */
    public function chat(AssistantChatRequest $request): StreamedResponse|JsonResponse
    {
        $user = $request->user();

        if (!$user->pip_agreement_signed_at) {
            return response()->json([
                'error' => 'You must accept the Pip terms before using the assistant.',
                'code' => 'pip_agreement_required',
            ], 403);
        }

        $messages = $request->validated()['messages'];

        return response()->stream(function () use ($messages, $user) {
            // Release the session lock so other browser tabs remain responsive
            session()->save();

            // Clear any buffering that would delay SSE delivery
            if (ob_get_level()) {
                ob_end_clean();
            }

            $emit = function (string $type, mixed $data): void {
                echo 'data: '.json_encode(['type' => $type, 'data' => $data])."\n\n";

                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            };

            try {
                $content = $this->assistantService->chat(
                    $messages,
                    $user,
                    fn (string $type, mixed $data) => $emit($type, $data)
                );

                $emit('content', ['text' => $content]);
                $emit('done', null);
            } catch (\RuntimeException $e) {
                Log::warning('AssistantController: handled exception', ['error' => $e->getMessage()]);
                $emit('error', ['message' => $e->getMessage()]);
            } catch (\Throwable $e) {
                Log::error('AssistantController: unexpected error', ['error' => $e->getMessage()]);
                $emit('error', ['message' => 'An unexpected error occurred. Please try again.']);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Record the user's acceptance of the Pip terms. Required before chatting.
     */
    public function acceptAgreement(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->pip_agreement_signed_at) {
            $user->pip_agreement_signed_at = now();
            $user->save();
        }

        return response()->json([
            'pip_agreement_signed_at' => $user->pip_agreement_signed_at,
        ]);
    }

    /**
     * Record a thumbs-up / thumbs-down rating on a Pip reply, along with the full
     * conversation transcript so it can be audited later. Thumbs-down responses
     * are the ones we expect reviewers to spend time on.
     */
    public function feedback(AssistantFeedbackRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $messages = $data['messages'];
        $ratedReply = null;
        for ($i = count($messages) - 1; $i >= 0; --$i) {
            if (($messages[$i]['role'] ?? null) === 'assistant') {
                $ratedReply = (string) ($messages[$i]['content'] ?? '');
                break;
            }
        }

        $feedback = PipFeedback::create([
            'user_id' => $user->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'transcript' => $messages,
            'rated_reply' => $ratedReply,
        ]);

        if ($feedback->rating < 0) {
            Log::info('Pip feedback: thumbs-down recorded', [
                'feedback_id' => $feedback->id,
                'user_id' => $user->id,
                'message_count' => count($messages),
            ]);
        }

        return response()->json([
            'id' => $feedback->id,
            'rating' => $feedback->rating,
            'created_at' => $feedback->created_at,
        ], 201);
    }
}
