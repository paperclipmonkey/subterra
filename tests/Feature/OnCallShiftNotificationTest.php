<?php

namespace Tests\Feature;

use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\SlackAlerts\Facades\SlackAlert;
use Tests\TestCase;

class OnCallShiftNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SlackAlert::fake();
        config()->set('slack-alerts.webhook_urls.callouts', 'https://hooks.slack.com/services/test/callouts');
    }

    public function test_creating_future_shift_does_not_send_immediate_slack_alert()
    {
        $admin = User::factory()->dutyOfficer()->create();

        $this->actingAs($admin)->postJson('/api/admin/shifts', [
            'user_id' => $admin->id,
            'start_at' => now()->addHours(2)->toDateTimeString(),
            'end_at' => now()->addHours(10)->toDateTimeString(),
        ]);

        SlackAlert::expectNoMessagesSent();
    }

    public function test_command_sends_slack_alert_when_shift_starts()
    {
        $user = User::factory()->dutyOfficer()->create(['name' => 'Michael Waterworth']);

        $shift = OnCallShift::create([
            'user_id' => $user->id,
            'start_at' => now()->subMinutes(5),
            'end_at' => now()->addHours(8),
        ]);

        $this->artisan('shifts:notify-started')
            ->expectsOutput("Notified for shift ID: {$shift->id}")
            ->assertExitCode(0);

        SlackAlert::expectMessagesSent(function ($message) {
            return str_contains($message['text'], 'DUTY OFFICER UPDATE') &&
                   str_contains($message['text'], 'Michael Waterworth is now ON CALL');
        });

        $this->assertNotNull($shift->refresh()->notified_at);
    }

    public function test_command_does_not_send_duplicate_alerts()
    {
        $user = User::factory()->dutyOfficer()->create();

        $shift = OnCallShift::create([
            'user_id' => $user->id,
            'start_at' => now()->subMinutes(5),
            'end_at' => now()->addHours(8),
            'notified_at' => now()->subMinutes(2),
        ]);

        $this->artisan('shifts:notify-started')
            ->doesntExpectOutput("Notified for shift ID: {$shift->id}")
            ->assertExitCode(0);

        SlackAlert::expectNoMessagesSent();
    }

    public function test_command_ignores_future_shifts()
    {
        $user = User::factory()->dutyOfficer()->create();

        $shift = OnCallShift::create([
            'user_id' => $user->id,
            'start_at' => now()->addMinutes(30),
            'end_at' => now()->addHours(8),
        ]);

        $this->artisan('shifts:notify-started')
            ->doesntExpectOutput("Notified for shift ID: {$shift->id}")
            ->assertExitCode(0);

        SlackAlert::expectNoMessagesSent();
        $this->assertNull($shift->refresh()->notified_at);
    }
}
