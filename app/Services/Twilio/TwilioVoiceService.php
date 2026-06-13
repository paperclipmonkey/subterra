<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use App\Contracts\VoiceCaller;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Twilio programmable-voice provider. Places an outbound call that fetches TwiML from our
 * own webhook (spoken alert + "press 1 to acknowledge" gather).
 */
class TwilioVoiceService implements VoiceCaller
{
    private string $baseUrl = 'https://api.twilio.com/2010-04-01';

    public function call(string $to, string $twimlUrl): ?string
    {
        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.token');
        $from = (string) config('services.twilio.from');

        if (!config('services.twilio.enabled')) {
            Log::info('Twilio disabled (services.twilio.enabled=false); voice call not placed.', ['to' => $to]);

            return null;
        }

        if ($sid === '' || $token === '' || $from === '') {
            Log::warning('Twilio credentials not configured. Voice call not placed.');

            return null;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($sid, $token)
                ->timeout(10)
                ->retry(3, 250, throw: false)
                ->post("{$this->baseUrl}/Accounts/{$sid}/Calls.json", [
                    'From' => $from,
                    'To' => $to,
                    'Url' => $twimlUrl,
                    'Method' => 'POST',
                ]);

            if ($response->successful()) {
                $callSid = $response->json('sid');
                Log::info('Twilio voice call placed.', ['sid' => $callSid, 'to_url' => $twimlUrl]);

                return $callSid;
            }

            Log::error('Twilio voice API error: '.$response->status().' '.$response->body());

            return null;
        } catch (Exception $e) {
            Log::error('Twilio voice exception: '.$e->getMessage());

            return null;
        }
    }
}
