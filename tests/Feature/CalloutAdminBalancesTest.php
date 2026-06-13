<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalloutAdminBalancesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_callout_dashboard_includes_sms_balances()
    {
        Config::set('services.twilio.sid', 'AC_test');
        Config::set('services.twilio.token', 'token');
        Config::set('services.textmagic.username', 'tm');
        Config::set('services.textmagic.api_key', 'key');
        Config::set('callouts.balance.cache_seconds', 0);

        Http::fake([
            'api.twilio.com/*' => Http::response(['balance' => '20.00', 'currency' => 'USD']),
            'rest.textmagic.com/*' => Http::response(['balance' => 40, 'currency' => 'GBP']),
        ]);

        $officer = User::factory()->dutyOfficer()->create();

        $this->actingAs($officer)
            ->getJson('/api/admin/callouts')
            ->assertOk()
            ->assertJsonPath('sms_balances.primary.provider', 'Twilio')
            ->assertJsonPath('sms_balances.primary.reachable', true)
            ->assertJsonPath('sms_balances.primary.ok', true)
            ->assertJsonPath('sms_balances.backup.provider', 'TextMagic')
            ->assertJsonPath('sms_balances.backup.ok', true);
    }
}
