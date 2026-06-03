<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use App\Contracts\SmsSender;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Twilio SMS provider (REST API via the HTTP client — no SDK dependency, mirroring the
 * TextMagic integration on the backup and keeping it trivially fakeable in tests).
 */
class TwilioSmsService implements SmsSender
{
    private string $baseUrl = 'https://api.twilio.com/2010-04-01';

    public function send(string $to, string $message): bool
    {
        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.token');
        $from = (string) config('services.twilio.from');

        if (! config('services.twilio.enabled')) {
            Log::info('Twilio disabled (services.twilio.enabled=false); SMS not sent.', ['to' => $to]);

            return false;
        }

        if ($sid === '' || $token === '' || $from === '') {
            Log::warning('Twilio credentials not configured. SMS not sent.');

            return false;
        }

        try {
            // Bounded timeout + retry on transient connection failures, so a slow provider
            // can never block the synchronous alert loop (mirrors the old ClickSend client).
            $response = Http::asForm()
                ->withBasicAuth($sid, $token)
                ->timeout(10)
                ->retry(3, 250, throw: false)
                ->post("{$this->baseUrl}/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To' => $to,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                Log::info('Twilio SMS sent.', ['sid' => $response->json('sid')]);

                return true;
            }

            Log::error('Twilio SMS API error: '.$response->status().' '.$response->body());

            return false;
        } catch (Exception $e) {
            Log::error('Twilio SMS exception: '.$e->getMessage());

            return false;
        }
    }
}
