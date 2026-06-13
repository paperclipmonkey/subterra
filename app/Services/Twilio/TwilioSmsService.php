<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use App\Contracts\SmsSender;
use App\Models\SmsMessage;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Twilio SMS provider (REST API via the HTTP client — no SDK dependency, mirroring the
 * TextMagic integration on the backup and keeping it trivially fakeable in tests).
 *
 * Each outbound message is recorded as an SmsMessage and asks Twilio to POST delivery
 * updates to our status-callback webhook, so we can track whether alerts actually arrived.
 */
class TwilioSmsService implements SmsSender
{
    private string $baseUrl = 'https://api.twilio.com/2010-04-01';

    public function send(string $to, string $message, array $context = []): bool
    {
        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.token');
        $from = (string) config('services.twilio.from');

        if (!config('services.twilio.enabled')) {
            Log::info('Twilio disabled (services.twilio.enabled=false); SMS not sent.', ['to' => $to]);

            return false;
        }

        if ($sid === '' || $token === '' || $from === '') {
            Log::warning('Twilio credentials not configured. SMS not sent.');

            return false;
        }

        try {
            $payload = [
                'From' => $from,
                'To' => $to,
                'Body' => $message,
            ];

            // Ask Twilio to POST delivery updates to our webhook (skip if we can't build a URL).
            $statusCallback = $this->statusCallbackUrl();
            if ($statusCallback !== null) {
                $payload['StatusCallback'] = $statusCallback;
            }

            // Bounded timeout + retry on transient connection failures, so a slow provider
            // can never block the synchronous alert loop (mirrors the old ClickSend client).
            $response = Http::asForm()
                ->withBasicAuth($sid, $token)
                ->timeout(10)
                ->retry(3, 250, throw: false)
                ->post("{$this->baseUrl}/Accounts/{$sid}/Messages.json", $payload);

            if ($response->successful()) {
                $providerSid = $response->json('sid');
                Log::info('Twilio SMS sent.', ['sid' => $providerSid]);
                $this->record($to, $context, $providerSid, $response->json('status', 'queued'));

                return true;
            }

            Log::error('Twilio SMS API error: '.$response->status().' '.$response->body());
            $this->record($to, $context, null, 'rejected', (string) $response->json('code'));

            return false;
        } catch (Exception $e) {
            Log::error('Twilio SMS exception: '.$e->getMessage());

            return false;
        }
    }

    /** The webhook Twilio POSTs delivery status updates to, or null if not buildable. */
    private function statusCallbackUrl(): ?string
    {
        $secret = (string) config('services.twilio.webhook_secret');
        if ($secret === '') {
            return null;
        }

        try {
            return route('webhooks.twilio.sms.status', ['secret' => $secret]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Persist the message so its delivery can be tracked. Never lets a logging/DB failure
     * break the safety-critical alert path.
     *
     * @param  array<string, mixed>  $context
     */
    private function record(string $to, array $context, ?string $providerSid, string $status, ?string $errorCode = null): void
    {
        try {
            SmsMessage::create([
                'provider' => 'twilio',
                'provider_sid' => $providerSid,
                'to_masked' => SmsMessage::maskNumber($to),
                'recipient_name' => $context['recipient_name'] ?? null,
                'user_id' => $context['user_id'] ?? null,
                'callout_id' => $context['callout_id'] ?? null,
                'incident_id' => $context['incident_id'] ?? null,
                'context' => $context['label'] ?? null,
                'status' => $status,
                'error_code' => $errorCode,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record outbound SMS for delivery tracking: '.$e->getMessage());
        }
    }
}
