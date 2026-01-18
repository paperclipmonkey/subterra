<?php

namespace App\Services;

use OpenAPI\Client\Api\MessagesApi;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\Message;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class SmsService
{
    private MessagesApi $apiInstance;
    private string $senderId;

    public function __construct()
    {
        $config = Configuration::getDefaultConfiguration()
            ->setApiKey('Authorization', config('services.sms_works.api_key'));
        
        // The SDK uses Guzzle, but sometimes we need to inject our own client if needed.
        // For now, default is fine.
        $this->apiInstance = new MessagesApi(new Client(), $config);
        $this->senderId = config('services.sms_works.sender_id', 'Subterra');
    }

    /**
     * Send an SMS to a single recipient.
     *
     * @param string $to The phone number (e.g., "447777777777")
     * @param string $content The message body
     * @return object The API response
     * @throws Exception
     */
    public function sendMessage(string $to, string $content): object
    {
        $message = new Message();
        $message->setSender($this->senderId);
        $message->setDestination($to);
        $message->setContent($content); // SMS Works uses 'content' not 'message'
        
        if (!config('services.sms_works.enabled')) {
            Log::info("SMS Disabled. Would have sent to {$to}: {$content}");
            // Return a dummy object explicitly mimicking the expected response structure if needed, 
            // or just a generic object if the caller doesn't strictly type check beyond 'object'.
            // The real response has a getMessageid() method.
            return new class {
                public function getMessageid() { return 'SKIPPED-SMS-DISABLED'; }
            };
        }

        try {
            $response = $this->apiInstance->messageSendPost($message);
            Log::info("SMS sent to {$to}: " . $response->getMessageid());
            return $response;
        } catch (Exception $e) {
            Log::error("Failed to send SMS to {$to}: " . $e->getMessage());
            throw $e;
        }
    }
}
