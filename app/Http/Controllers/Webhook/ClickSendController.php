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

        // Normalize Phone Number (ClickSend usually sends E.164)

        // Check for specific "OUT SAFE" command
        $isOutSafe = Str::of($body)->trim()->upper()->is('OUT SAFE');

        if ($isOutSafe) {
            $this->handleOutSafe($from, $body);
        } else {
            $this->handleGenericMessage($from, $body);
        }

        return response()->json(['status' => 'success']);
    }

    private function handleOutSafe(string $from, string $body): void
    {
        // Find active callouts where a participant or user has this phone number
        $activeCallouts = Callout::query()
            ->whereIn('status', ['active', 'triggered'])
            ->where(function ($query) use ($from) {
                $query->whereHas('participants', function ($q) use ($from) {
                    $q->where('phone', $from);
                })
                ->orWhereHas('user', function ($q) use ($from) {
                    $q->where('phone', $from);
                });
            })
            ->get();

        if ($activeCallouts->isEmpty()) {
            Log::info("Received 'OUT SAFE' from {$from} but no active callout found.");

            // Optional: Reply "No active callout found for you."
            return;
        }

        // Since we now enforce that a phone number can only be in one active callout,
        // we can just grab the first one.
        $callout = $activeCallouts->first();

        Log::info("Cancelling Callout ID: {$callout->id} via SMS from {$from}");

        $callout->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'cancelled_location' => 'SMS',
        ]);

        // Add note to incident if exists
        if ($callout->incident) {
            $callout->incident->notes()->create([
                'content' => "Callout CANCELLED via SMS from {$from} saying 'OUT SAFE'.",
            ]);
            $callout->incident->update(['status' => 'resolved']);
        }
    }

    private function handleGenericMessage(string $from, string $body): void
    {
        // Find relevant context to attach the message to
        // If triggered, attach to Incident.
        // If active, attach to Callout (maybe log it?)

        $callouts = Callout::query()
            ->whereIn('status', ['active', 'triggered'])
            ->where(function ($query) use ($from) {
                $query->whereHas('participants', function ($q) use ($from) {
                    $q->where('phone', $from);
                })
                ->orWhereHas('user', function ($q) use ($from) {
                    $q->where('phone', $from);
                });
            })
            ->get();

        foreach ($callouts as $callout) {
            if ($callout->incident) {
                $callout->incident->notes()->create([
                    'content' => "SMS Received from {$from}: {$body}",
                ]);
            } else {
                // No incident yet (still in 15 min window?).
                // Append to team_details as requested.
                $newDetails = $callout->team_details."\n\n[SMS from {$from}]: {$body}";
                $callout->update(['team_details' => $newDetails]);

                Log::info("SMS for Callout {$callout->id} from {$from}: {$body} (Appended to team_details)");
            }
        }
    }
}
