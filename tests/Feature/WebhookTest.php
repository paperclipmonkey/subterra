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
    public function test_clicksend_webhook_cancels_callout_with_loose_text_and_normalized_phone()
    {
        $secret = 'test-secret';
        Config::set('services.clicksend.webhook_secret', $secret);

        $user = \App\Models\User::factory()->create(['phone' => '07777777777']); // Local format
        $callout = \App\Models\Callout::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'trip_plan' => 'Going caving'
        ]);

        // Incoming webhook has E.164 format and loose text format
        $response = $this->postJson('/api/webhooks/clicksend/sms', [
            'from' => '+447777777777',
            'body' => 'We are out safe thanks!',
            'secret' => $secret,
        ]);

        $response->assertStatus(200);

        $this->assertEquals('cancelled', $callout->fresh()->status);
        $this->assertDatabaseHas('trips', [
            'description' => 'Going caving'
        ]);
    }
    
    public function test_clicksend_webhook_handles_generic_message()
    {
        $secret = 'test-secret';
        Config::set('services.clicksend.webhook_secret', $secret);

        $user = \App\Models\User::factory()->create(['phone' => '+447777777777']);
        $callout = \App\Models\Callout::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'team_details' => 'Initial team info'
        ]);

        $response = $this->postJson('/api/webhooks/clicksend/sms', [
            'from' => '07777777777', // Matches because 10 digit normalized suffix matches
            'body' => 'We will be an hour late',
            'secret' => $secret,
        ]);

        $response->assertStatus(200);

        $callout->refresh();
        $this->assertEquals('active', $callout->status);
        $this->assertStringContainsString('We will be an hour late', $callout->team_details);
        $this->assertStringContainsString('Initial team info', $callout->team_details);
    }
}
