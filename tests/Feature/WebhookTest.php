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

    public function test_clicksend_webhook_cancels_callout_with_strict_text_and_normalized_phone()
    {
        $secret = 'test-secret';
        Config::set('services.clicksend.webhook_secret', $secret);

        $user = \App\Models\User::factory()->create(['phone' => '07777777777']); // Local format
        $callout = \App\Models\Callout::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'trip_plan' => 'Going caving',
        ]);

        $mock = \Mockery::mock(\App\Services\ClickSendService::class);
        $mock->shouldReceive('sendSms')
             ->once()
             ->with('+447777777777', 'Callout cancelled successfully. Glad you are safe.');
        $this->app->instance(\App\Services\ClickSendService::class, $mock);

        // Incoming webhook has E.164 format and correct text format (case insensitive)
        $response = $this->postJson('/api/webhooks/clicksend/sms', [
            'from' => '+447777777777',
            'body' => '   out safe ',
            'secret' => $secret,
        ]);

        $response->assertStatus(200);

        $this->assertEquals('cancelled', $callout->fresh()->status);
        $this->assertDatabaseHas('trips', [
            'description' => 'Going caving',
        ]);
    }

    public function test_clicksend_webhook_handles_generic_message_and_replies()
    {
        $secret = 'test-secret';
        Config::set('services.clicksend.webhook_secret', $secret);

        $user = \App\Models\User::factory()->create(['phone' => '+447777777777']);
        $callout = \App\Models\Callout::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'team_details' => 'Initial team info',
        ]);

        $mock = \Mockery::mock(\App\Services\ClickSendService::class);
        $mock->shouldReceive('sendSms')
             ->once()
             ->with('07777777777', "Message logged. Not cancelled. Reply exactly 'OUT SAFE' to cancel callout.");
        $this->app->instance(\App\Services\ClickSendService::class, $mock);

        // Testing loose "OUT SAFE" - should fall into generic handler now
        $response = $this->postJson('/api/webhooks/clicksend/sms', [
            'from' => '07777777777',
            'body' => 'Not out safe yet',
            'secret' => $secret,
        ]);

        $response->assertStatus(200);

        $callout->refresh();
        $this->assertEquals('active', $callout->status);
        $this->assertStringContainsString('Not out safe yet', $callout->team_details);
    }

    public function test_clicksend_webhook_aborts_without_callout_and_replies()
    {
        $this->withoutExceptionHandling();

        $secret = 'test-secret';
        Config::set('services.clicksend.webhook_secret', $secret);

        $mock = \Mockery::mock(\App\Services\ClickSendService::class);
        $mock->shouldReceive('sendSms')
             ->once()
             ->with('+447777777777', 'Callout not cancelled. No active callout found for this number.');
        $this->app->instance(\App\Services\ClickSendService::class, $mock);

        $response = $this->postJson('/api/webhooks/clicksend/sms', [
            'from' => '+447777777777',
            'body' => 'OUT SAFE',
            'secret' => $secret,
        ]);

        $response->assertStatus(200);
    }
}
