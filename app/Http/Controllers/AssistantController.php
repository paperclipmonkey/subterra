<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssistantChatRequest;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
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
        $user     = $request->user();
        $messages = $request->validated()['messages'];

        return response()->stream(function () use ($messages, $user) {
            // Release the session lock so other browser tabs remain responsive
            session()->save();

            // Clear any buffering that would delay SSE delivery
            if (ob_get_level()) {
                ob_end_clean();
            }

            $emit = function (string $type, mixed $data): void {
                echo 'data: ' . json_encode(['type' => $type, 'data' => $data]) . "\n\n";

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
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }
}
