<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssistantChatRequest;
use App\Http\Requests\AssistantFeedbackRequest;
use App\Models\PipFeedback;
use App\Services\AssistantService;
use App\Services\TripImport\InvalidLogbookException;
use App\Services\TripImport\TripImportService;
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
        $mode = $request->validated()['mode'] ?? AssistantService::MODE_DEFAULT;

        // Ordinary Pip users get the trip importer only. Data-steward mode
        // files data-fix proposals, and the planner recommends trips — both
        // are restricted to administrators.
        if (!in_array($mode, AssistantService::modesFor($user), true)) {
            return response()->json([
                'error' => $mode === AssistantService::MODE_DATA
                    ? 'Data-steward mode is restricted to administrators.'
                    : 'Pip can only help you import your trips.',
            ], 403);
        }

        $maxTurns = config(AssistantService::limitsKey($mode).'.max_user_turns');
        $userTurns = count(array_filter($messages, fn ($m) => ($m['role'] ?? null) === 'user'));
        if ($maxTurns !== null && $userTurns > (int) $maxTurns) {
            return response()->json([
                'error' => 'This conversation has reached its length limit. Start a new conversation to carry on — your import progress is saved.',
                'code' => 'conversation_limit',
            ], 422);
        }

        if (AssistantService::overDailyBudget($user)) {
            return response()->json([
                'error' => "You've used today's Pip allowance. It resets at midnight — your import progress is saved.",
                'code' => 'daily_budget_exceeded',
            ], 429);
        }

        return response()->stream(function () use ($messages, $user, $mode) {
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
                    fn (string $type, mixed $data) => $emit($type, $data),
                    $mode
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

    /**
     * Accept a CSV or TSV logbook upload and stage its trips server-side.
     *
     * The file never goes into the chat: it is parsed and matched here, and
     * Pip works from compact summaries of the staged rows. That keeps every
     * turn small (and cheap) however long the logbook is, and avoids the old
     * failure where a pasted CSV blew the 4,000-character message limit.
     */
    public function importLogbook(Request $request, TripImportService $imports): JsonResponse
    {
        $user = $request->user();

        if (!$user->pip_agreement_signed_at) {
            return response()->json([
                'error' => 'You must accept the Pip terms before using the assistant.',
                'code' => 'pip_agreement_required',
            ], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt,tsv'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');
        $content = file_get_contents($file->getPathname());

        if ($content === false || trim($content) === '') {
            return response()->json(['error' => 'The uploaded file appears to be empty.'], 422);
        }

        if (strlen($content) > 512_000) {
            return response()->json(['error' => 'File too large. Maximum 512 KB — split the logbook into several files.'], 422);
        }

        $filename = mb_substr($file->getClientOriginalName(), 0, 200);

        try {
            $staged = $imports->stageFile($user, $content, $filename);
        } catch (InvalidLogbookException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => 'invalid_logbook'], 422);
        }

        return response()->json([
            'import_id' => $staged['import']->id,
            'filename' => $filename,
            'rows_added' => $staged['added'],
            'rows_rejected' => $staged['rejected'],
            'parse' => $staged['parse'],
            'counts' => $imports->counts($staged['import']),
        ]);
    }

    /**
     * The user's import in progress, if any, so the UI can offer to resume it.
     */
    public function importStatus(Request $request, TripImportService $imports): JsonResponse
    {
        $import = $imports->openImportFor($request->user());

        return response()->json([
            'data' => $import ? [
                'import_id' => $import->id,
                'filename' => $import->filename,
                'default_visibility' => $import->default_visibility,
                'counts' => $imports->counts($import),
                'updated_at' => $import->updated_at,
            ] : null,
        ]);
    }
}
