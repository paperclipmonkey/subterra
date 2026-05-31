<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClickSendService
{
    protected string $baseUrl = 'https://rest.clicksend.com/v3';
    protected string $username;
    protected string $apiKey;

    public function __construct()
    {
        $this->username = config('services.clicksend.username');
        $this->apiKey = config('services.clicksend.api_key');
    }

    /**
     * Send an SMS to one or more recipients.
     *
     * @param string|array $to Phone number(s)
     * @param string $message Message body
     * @return bool
     */
    public function sendSms(string|array $to, string $message): bool
    {
        if (empty($this->username) || empty($this->apiKey)) {
            Log::warning('ClickSend credentials not configured. SMS not sent.');

            return false;
        }

        $recipients = is_array($to) ? $to : [$to];
        $messages = [];

        foreach ($recipients as $recipient) {
            $messages[] = [
                'source' => 'php',
                'body' => $message,
                'to' => $recipient,
            ];
        }

        try {
            $response = Http::withBasicAuth($this->username, $this->apiKey)
                ->post("{$this->baseUrl}/sms/send", [
                    'messages' => $messages,
                ]);

            if ($response->successful()) {
                Log::info('ClickSend SMS sent to '.count($recipients).' recipients.');

                return true;
            } else {
                Log::error('ClickSend API Error: '.$response->body());

                return false;
            }
        } catch (Exception $e) {
            Log::error('ClickSend Exception: '.$e->getMessage());

            return false;
        }
    }
}
