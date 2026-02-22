<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Callout;
use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClickSendController extends Controller
{
    /**
     * Handle incoming SMS from ClickSend.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handleSms(Request $request)
    {
        Log::info('ClickSend Webhook Received', $request->all());

        $from = $request->input('from'); // Sender's phone number
        $body = $request->input('body'); // Message body

        if (!$from || !$body) {
            return response()->json(['status' => 'error', 'message' => 'Missing from or body'], 400);
        }

        // Verify Secret - user puts ?secret=xyz in the ClickSend webhook URL
        $configuredSecret = config('services.clicksend.webhook_secret');
        if (empty($configuredSecret) || !hash_equals($configuredSecret, $request->input('secret') ?? '')) {
            Log::warning('ClickSend Webhook attempt with invalid secret', ['ip' => $request->ip()]);

            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // Normalize Phone Number: Strip all non-numeric characters, and grab the last 10 digits
        // E.g. +447... vs 07... will both map to the same 10 digits
        $normalizedFrom = preg_replace('/[^0-9]/', '', $from);
        if (strlen($normalizedFrom) > 10) {
            $normalizedFrom = substr($normalizedFrom, -10);
        }
        
        // Loose match for "out safe"
        $isOutSafe = Str::contains(Str::lower($body), 'out safe');

        if ($isOutSafe) {
            $this->handleOutSafe($from, $normalizedFrom, $body);
        } else {
            $this->handleGenericMessage($from, $normalizedFrom, $body);
        }

        return response()->json(['status' => 'success']);
    }

    private function handleOutSafe(string $originalFrom, string $normalizedFrom, string $body): void
    {
        // Find active callouts where a participant or user has this phone number
        $activeCallouts = Callout::query()
            ->whereIn('status', ['active', 'triggered'])
            ->where(function ($query) use ($normalizedFrom) {
                // If the user's phone contains the normalized numeric string
                $query->whereHas('participants', function ($q) use ($normalizedFrom) {
                    $q->where('phone', 'like', "%{$normalizedFrom}");
                })
                ->orWhereHas('user', function ($q) use ($normalizedFrom) {
                    $q->where('phone', 'like', "%{$normalizedFrom}");
                });
            })
            ->get();

        if ($activeCallouts->isEmpty()) {
            $msg = "Received 'OUT SAFE' from {$originalFrom} but no active callout found.";
            Log::info($msg);
            throw new \RuntimeException($msg);
        }

        $callout = $activeCallouts->first();
        Log::info("Cancelling Callout ID: {$callout->id} via SMS from {$originalFrom}");

        // Use proper service to trigger watchdog, trip logging, and emails
        app(\App\Services\CalloutService::class)->cancel($callout);
        
        // Retain the SMS metadata
        $callout->update(['cancelled_location' => 'SMS']);

        // Add note to incident if exists (CalloutService handles the safe note, but we add SMS specificity)
        if ($callout->incident()->exists()) {
            $callout->incident->notes()->create([
                'user_id' => null, // System note
                'content' => "Callout CANCELLED via SMS from {$originalFrom} saying 'OUT SAFE'.",
            ]);
            $callout->incident->update(['status' => 'resolved']);
        }
    }

    private function handleGenericMessage(string $originalFrom, string $normalizedFrom, string $body): void
    {
        // Find relevant context to attach the message to
        $callouts = Callout::query()
            ->whereIn('status', ['active', 'triggered'])
            ->where(function ($query) use ($normalizedFrom) {
                $query->whereHas('participants', function ($q) use ($normalizedFrom) {
                    $q->where('phone', 'like', "%{$normalizedFrom}");
                })
                ->orWhereHas('user', function ($q) use ($normalizedFrom) {
                    $q->where('phone', 'like', "%{$normalizedFrom}");
                });
            })
            ->get();
            
        if ($callouts->isEmpty()) {
            $msg = "Received generic SMS from {$originalFrom} ({$body}) but no active callout found.";
            Log::info($msg);
            throw new \RuntimeException($msg);
        }

        foreach ($callouts as $callout) {
            if ($callout->incident) {
                $callout->incident->notes()->create([
                    'content' => "SMS Received from {$originalFrom}: {$body}",
                ]);
            } else {
                // No incident yet (still in 15 min window?).
                // Append to team_details as requested.
                $newDetails = $callout->team_details."\n\n[SMS from {$originalFrom}]: {$body}";
                $callout->update(['team_details' => $newDetails]);

                Log::info("SMS for Callout {$callout->id} from {$originalFrom}: {$body} (Appended to team_details)");
            }
        }
    }
}
