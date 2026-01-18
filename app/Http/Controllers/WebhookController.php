<?php

namespace App\Http\Controllers;

use App\Services\CalloutService;
use App\Models\Callout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected CalloutService $calloutService;

    public function __construct(CalloutService $calloutService)
    {
        $this->calloutService = $calloutService;
    }

    /**
     * Handle incoming SMS webhook.
     */
    public function handleIncomingSms(Request $request)
    {
        // Log the incoming request for debugging
        Log::info('Incoming SMS Webhook:', $request->all());

        // Validate payload structure (The SMS Works typically calls this 'content' and 'sender')
        // Example: {"sender": "447777777777", "content": "SAFE", "destination": "44712345678"}
        
        $sender = $request->input('sender');
        $content = trim(strtoupper($request->input('content', '')));

        if (!$sender || !$content) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        // Check for "SAFE" keyword
        if ($content === 'SAFE') {
            return $this->processSafeReply($sender);
        }

        return response()->json(['message' => 'Ignored'], 200);
    }

    private function processSafeReply(string $phone)
    {
        // Find active callout for this phone number
        // We match the user's phone number to the sender
        $callout = Callout::active()
            ->whereHas('user', function ($query) use ($phone) {
                $query->where('phone', $phone);
            })
            ->first();

        if ($callout) {
            Log::info("Processing SAFE reply for Callout ID: {$callout->id}");
            $this->calloutService->cancel($callout);
            return response()->json(['message' => 'Callout cancelled'], 200);
        }

        Log::warning("Received SAFE reply from {$phone} but no active callout found.");
        return response()->json(['message' => 'No active callout found'], 404);
    }
}
