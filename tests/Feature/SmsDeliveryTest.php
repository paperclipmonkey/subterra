<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Models\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.twilio.sid', 'AC_test');
        Config::set('services.twilio.token', 'token');
        Config::set('services.twilio.from', '+447000000000');
        Config::set('services.twilio.enabled', true);
        Config::set('services.twilio.webhook_secret', 'sekret');
    }

    public function test_outbound_sms_is_recorded_with_sid_masked_number_and_status_callback()
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123', 'status' => 'queued']),
        ]);

        $sent = app(SmsSender::class)->send('+447700900123', 'Test alert', [
            'label' => 'overdue_do',
            'callout_id' => 'callout-abc',
            'recipient_name' => 'Bob',
            'user_id' => 'user-1',
        ]);

        $this->assertTrue($sent);

        $this->assertDatabaseHas('sms_messages', [
            'provider' => 'twilio',
            'provider_sid' => 'SM123',
            'to_masked' => '•••• 0123',
            'recipient_name' => 'Bob',
            'callout_id' => 'callout-abc',
            'context' => 'overdue_do',
            'status' => 'queued',
        ]);

        // Twilio was asked to report delivery status back to our webhook.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'Messages.json')
                && isset($request->data()['StatusCallback'])
                && str_contains($request->data()['StatusCallback'], '/sms/status');
        });
    }

    public function test_status_callback_marks_message_delivered()
    {
        $message = SmsMessage::create([
            'provider' => 'twilio',
            'provider_sid' => 'SM999',
            'to_masked' => '•••• 0123',
            'status' => 'queued',
        ]);

        $this->postJson('/api/webhooks/twilio/sekret/sms/status', [
            'MessageSid' => 'SM999',
            'MessageStatus' => 'delivered',
        ])->assertNoContent();

        $message->refresh();
        $this->assertSame('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);
    }

    public function test_status_callback_records_failure_with_error_code()
    {
        $message = SmsMessage::create([
            'provider' => 'twilio',
            'provider_sid' => 'SMfail',
            'status' => 'sent',
        ]);

        $this->postJson('/api/webhooks/twilio/sekret/sms/status', [
            'MessageSid' => 'SMfail',
            'MessageStatus' => 'undelivered',
            'ErrorCode' => '30008',
        ])->assertNoContent();

        $message->refresh();
        $this->assertSame('undelivered', $message->status);
        $this->assertNotNull($message->failed_at);
        $this->assertSame('30008', $message->error_code);
    }

    public function test_late_earlier_stage_callback_does_not_regress_terminal_status()
    {
        // Twilio callbacks can arrive out of order: a late 'sent' must not overwrite a
        // terminal 'delivered' (or 'failed'/'undelivered') status.
        $message = SmsMessage::create([
            'provider' => 'twilio',
            'provider_sid' => 'SMlate',
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $this->postJson('/api/webhooks/twilio/sekret/sms/status', [
            'MessageSid' => 'SMlate',
            'MessageStatus' => 'sent',
        ])->assertNoContent();

        $this->assertSame('delivered', $message->fresh()->status);
    }

    public function test_status_callback_rejects_a_bad_secret()
    {
        $this->postJson('/api/webhooks/twilio/wrong-secret/sms/status', [
            'MessageSid' => 'SM999',
            'MessageStatus' => 'delivered',
        ])->assertForbidden();
    }
}
