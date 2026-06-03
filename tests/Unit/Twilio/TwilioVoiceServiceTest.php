<?php

declare(strict_types=1);

namespace Tests\Unit\Twilio;

use App\Services\Twilio\TwilioVoiceService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwilioVoiceServiceTest extends TestCase
{
    private function configure(bool $enabled = true): void
    {
        Config::set('services.twilio.sid', 'AC123');
        Config::set('services.twilio.token', 'tok');
        Config::set('services.twilio.from', '+447000000000');
        Config::set('services.twilio.enabled', $enabled);
    }

    public function test_places_call_and_returns_sid_when_enabled()
    {
        $this->configure();
        Http::fake(['*/Calls.json' => Http::response(['sid' => 'CA1'], 201)]);

        $sid = (new TwilioVoiceService())->call('+447111111111', 'https://app.test/twiml');

        $this->assertSame('CA1', $sid);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/Accounts/AC123/Calls.json')
            && $r['To'] === '+447111111111'
            && $r['Url'] === 'https://app.test/twiml'
            && $r['From'] === '+447000000000');
    }

    public function test_disabled_returns_null_and_sends_nothing()
    {
        $this->configure(false);
        Http::fake();

        $this->assertNull((new TwilioVoiceService())->call('+447111111111', 'https://app.test/twiml'));
        Http::assertNothingSent();
    }

    public function test_server_error_returns_null()
    {
        $this->configure();
        Http::fake(['*/Calls.json' => Http::response(['message' => 'boom'], 500)]);

        $this->assertNull((new TwilioVoiceService())->call('+447111111111', 'https://app.test/twiml'));
    }
}
