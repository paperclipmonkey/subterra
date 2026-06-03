<?php

declare(strict_types=1);

namespace Tests\Unit\Twilio;

use App\Services\Twilio\TwilioSmsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwilioSmsServiceTest extends TestCase
{
    private function configure(bool $enabled = true): void
    {
        Config::set('services.twilio.sid', 'AC123');
        Config::set('services.twilio.token', 'tok');
        Config::set('services.twilio.from', '+447000000000');
        Config::set('services.twilio.enabled', $enabled);
    }

    public function test_sends_when_enabled_and_configured()
    {
        $this->configure();
        Http::fake(['*/Messages.json' => Http::response(['sid' => 'SM1'], 201)]);

        $this->assertTrue((new TwilioSmsService())->send('+447111111111', 'hi'));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/Accounts/AC123/Messages.json')
            && $r['To'] === '+447111111111'
            && $r['Body'] === 'hi'
            && $r['From'] === '+447000000000');
    }

    public function test_disabled_returns_false_and_sends_nothing()
    {
        $this->configure(false);
        Http::fake();

        $this->assertFalse((new TwilioSmsService())->send('+447111111111', 'hi'));
        Http::assertNothingSent();
    }

    public function test_missing_credentials_returns_false()
    {
        Config::set('services.twilio.sid', '');
        Config::set('services.twilio.token', '');
        Config::set('services.twilio.from', '');
        Config::set('services.twilio.enabled', true);
        Http::fake();

        $this->assertFalse((new TwilioSmsService())->send('+447111111111', 'hi'));
        Http::assertNothingSent();
    }

    public function test_server_error_returns_false_without_throwing()
    {
        $this->configure();
        Http::fake(['*/Messages.json' => Http::response(['message' => 'boom'], 500)]);

        $this->assertFalse((new TwilioSmsService())->send('+447111111111', 'hi'));
    }

    public function test_connection_failure_is_handled_gracefully()
    {
        $this->configure();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertFalse((new TwilioSmsService())->send('+447111111111', 'hi'));
    }
}
