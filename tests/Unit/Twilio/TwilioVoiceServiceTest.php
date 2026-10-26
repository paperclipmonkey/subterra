<?php

declare(strict_types=1);

namespace Tests\Unit\Twilio;

use App\Services\Twilio\TwilioVoiceService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    public function test_error_log_does_not_contain_the_recipient_number()
    {
        // Error logs are forwarded to Slack, and Twilio echoes the number back.
        $this->configure();
        Http::fake(['*/Calls.json' => Http::response([
            'code' => 21211,
            'message' => "The 'To' number +447111111111 is not a valid phone number.",
        ], 400)]);
        Log::spy();

        $this->assertNull((new TwilioVoiceService())->call('+447111111111', 'https://app.test/webhooks/twilio/s3cret/voice'));

        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context = []) {
            $logged = $message.json_encode($context);

            return !str_contains($logged, '7111111111')
                && !str_contains($logged, 's3cret')
                && ($context['code'] ?? null) === 21211;
        })->once();
    }
}
