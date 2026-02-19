<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

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
