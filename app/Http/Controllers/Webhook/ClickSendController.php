<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Callout;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
        Log::info('ClickSend Webhook Received', Arr::except($request->all(), ['secret']));

        $from = $request->input('from'); // Sender's phone number
        $body = $request->input('body'); // Message body

        if (!$from || !$body) {
            return response()->json(['status' => 'error', 'message' => 'Missing from or body'], 400);
        }

        // Verify Secret
        $configuredSecret = config('services.clicksend.webhook_secret');
        if (empty($configuredSecret) || !hash_equals($configuredSecret, $request->input('secret') ?? '')) {
            Log::warning('ClickSend Webhook attempt with invalid secret', ['ip' => $request->ip()]);

            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // Normalize Phone Number: Strip all non-numeric characters, and grab the last 10 digits
        $normalizedFrom = preg_replace('/[^0-9]/', '', $from);
        if (strlen($normalizedFrom) > 10) {
            $normalizedFrom = substr($normalizedFrom, -10);
        }

        // 1. Find active callouts related to this phone number
        $activeCallouts = Callout::query()
            ->whereIn('status', ['active', 'triggered'])
            ->where(function ($query) use ($normalizedFrom) {
                $query->whereHas('participants', function ($q) use ($normalizedFrom) {
                    $q->where('phone', 'like', "%{$normalizedFrom}")
                      ->orWhereHas('user', function ($q2) use ($normalizedFrom) {
                          $q2->where('phone', 'like', "%{$normalizedFrom}");
                      });
                })
                ->orWhereHas('user', function ($q) use ($normalizedFrom) {
                    $q->where('phone', 'like', "%{$normalizedFrom}");
                });
            })
            ->get();

        // 2. If no callout is found, inform the user and abort
        if ($activeCallouts->isEmpty()) {
            $msg = "Received SMS from {$from} ('{$body}') but no active callout found.";
            Log::error($msg);

            app(\App\Services\ClickSendService::class)->sendSms(
                $from,
                'Callout not cancelled. No active callout found for this number.'
            );

            return response()->json(['status' => 'success']);
        }

        // Note: For now, we assume a phone number can only have ONE active callout.
        $callout = $activeCallouts->first();

        // 3. Strict match for "OUT SAFE" (case-insensitive, ignores surrounding whitespace)
        $isOutSafe = Str::of($body)->trim()->upper()->is('OUT SAFE');

        if ($isOutSafe) {
            $this->processCancellation($callout, $from, $body);
        } else {
            $this->processGenericMessage($activeCallouts, $from, $body);
        }

        return response()->json(['status' => 'success']);
    }

    private function processCancellation(Callout $callout, string $originalFrom, string $body): void
    {
        Log::info("Cancelling Callout ID: {$callout->id} via SMS from {$originalFrom}");

        // Use proper service to trigger watchdog, trip logging, and emails
        app(\App\Services\CalloutService::class)->cancel($callout);

        // Retain the SMS metadata
        $callout->update(['cancelled_location' => 'SMS']);

        if ($callout->incident()->exists()) {
            $callout->incident->notes()->create([
                'user_id' => null,
                'content' => "Callout CANCELLED via SMS from {$originalFrom} saying 'OUT SAFE'.",
            ]);
            $callout->incident->update(['status' => 'resolved']);
        }

        app(\App\Services\ClickSendService::class)->sendSms(
            $originalFrom,
            'Callout cancelled successfully. Glad you are safe.'
        );
    }

    private function processGenericMessage(\Illuminate\Support\Collection $callouts, string $originalFrom, string $body): void
    {
        foreach ($callouts as $callout) {
            if ($callout->incident) {
                $callout->incident->notes()->create([
                    'content' => "SMS Received from {$originalFrom}: {$body}",
                ]);
            } else {
                $newDetails = $callout->team_details."\n\n[SMS from {$originalFrom}]: {$body}";
                $callout->update(['team_details' => $newDetails]);

                Log::info("SMS for Callout {$callout->id} from {$originalFrom}: {$body} (Appended to team_details)");
            }
        }

        // Ask the user to clarify to prevent ghost cancellations
        app(\App\Services\ClickSendService::class)->sendSms(
            $originalFrom,
            "Message logged. Not cancelled. Reply exactly 'OUT SAFE' to cancel callout."
        );
    }
}
