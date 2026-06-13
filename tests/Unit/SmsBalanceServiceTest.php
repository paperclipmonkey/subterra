<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sms\SmsBalanceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsBalanceServiceTest extends TestCase
{
    private SmsBalanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.twilio.sid', 'AC_test');
        Config::set('services.twilio.token', 'token');
        Config::set('services.textmagic.username', 'tm_user');
        Config::set('services.textmagic.api_key', 'tm_key');
        Config::set('callouts.balance.primary_min', 5);
        Config::set('callouts.balance.backup_min', 5);
        Config::set('callouts.balance.cache_seconds', 0);
        Cache::flush();
        $this->service = new SmsBalanceService();
    }

    public function test_reports_balances_and_ok_when_above_minimum()
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['balance' => '12.50', 'currency' => 'USD']),
            'rest.textmagic.com/*' => Http::response(['balance' => 30, 'currency' => 'GBP']),
        ]);

        $statuses = $this->service->providerStatuses();

        $this->assertSame(12.5, $statuses['primary']['amount']);
        $this->assertSame('USD', $statuses['primary']['currency']);
        $this->assertTrue($statuses['primary']['ok']);
        $this->assertSame(30.0, $statuses['backup']['amount']);
        $this->assertTrue($statuses['backup']['ok']);
        $this->assertSame([], $this->service->blockingProviders());
    }

    public function test_flags_provider_below_minimum_as_blocking()
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['balance' => '1.00', 'currency' => 'USD']),
            'rest.textmagic.com/*' => Http::response(['balance' => 30, 'currency' => 'GBP']),
        ]);

        $statuses = $this->service->providerStatuses();

        $this->assertFalse($statuses['primary']['ok']);
        $this->assertTrue($statuses['primary']['reachable']);
        $this->assertTrue($statuses['backup']['ok']);
        $this->assertSame(['primary'], $this->service->blockingProviders());
    }

    public function test_unreachable_balance_is_not_treated_as_blocking()
    {
        Http::fake([
            'api.twilio.com/*' => Http::response('error', 500),
            'rest.textmagic.com/*' => Http::response(['balance' => 30, 'currency' => 'GBP']),
        ]);

        $statuses = $this->service->providerStatuses();

        $this->assertNull($statuses['primary']['amount']);
        $this->assertFalse($statuses['primary']['reachable']);
        $this->assertFalse($statuses['primary']['ok']);
        // A balance we can't read must NOT block callouts.
        $this->assertSame([], $this->service->blockingProviders());
    }

    public function test_unconfigured_provider_is_unreachable_and_not_blocking()
    {
        Config::set('services.textmagic.username', null);
        Config::set('services.textmagic.api_key', null);
        Http::fake([
            'api.twilio.com/*' => Http::response(['balance' => '12.50', 'currency' => 'USD']),
        ]);

        $statuses = $this->service->providerStatuses();

        $this->assertFalse($statuses['backup']['reachable']);
        $this->assertSame([], $this->service->blockingProviders());
    }
}
