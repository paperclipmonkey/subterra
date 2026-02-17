<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    // ========================================================================
    // SMS Webhook Authentication (SMSWorks)
    // ========================================================================

    public function test_sms_webhook_rejects_request_without_secret()
    {
        Config::set('services.sms_works.webhook_secret', 'test-secret');

        $response = $this->postJson('/api/webhooks/incoming-sms', [
            'sender' => '+447777777777',
            'content' => 'SAFE',
        ]);

        $response->assertStatus(401);
    }

    public function test_sms_webhook_rejects_request_with_wrong_secret()
    {
        Config::set('services.sms_works.webhook_secret', 'correct-secret');

        $response = $this->postJson('/api/webhooks/incoming-sms', [
            'sender' => '+447777777777',
            'content' => 'SAFE',
        ], ['X-Webhook-Secret' => 'wrong-secret']);

        $response->assertStatus(401);
    }

    public function test_sms_webhook_rejects_when_no_secret_configured()
    {
        Config::set('services.sms_works.webhook_secret', null);

        $response = $this->postJson('/api/webhooks/incoming-sms', [
            'sender' => '+447777777777',
            'content' => 'SAFE',
        ]);

        $response->assertStatus(401);
    }

    // ========================================================================
    // ClickSend Webhook Secret Bypass
    // ========================================================================

    public function test_clicksend_webhook_rejects_when_no_secret_configured()
    {
        Config::set('services.clicksend.webhook_secret', null);

        $response = $this->postJson('/api/webhooks/clicksend/sms', [
            'from' => '+447777777777',
            'body' => 'OUT SAFE',
        ]);

        $response->assertStatus(401);
    }

    public function test_clicksend_webhook_rejects_empty_string_secret()
    {
        Config::set('services.clicksend.webhook_secret', '');

        $response = $this->postJson('/api/webhooks/clicksend/sms', [
            'from' => '+447777777777',
            'body' => 'OUT SAFE',
        ]);

        $response->assertStatus(401);
    }
}
