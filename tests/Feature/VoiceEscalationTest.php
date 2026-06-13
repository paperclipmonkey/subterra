<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\VoiceCaller;
use App\Models\Callout;
use App\Models\Cave;
use App\Models\Incident;
use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VoiceEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cave::factory()->create();
        Notification::fake();
        Carbon::setTestNow('2025-01-01 12:00:00');
        Config::set('callouts.escalation', [
            'voice_after_minutes' => 3,
            'voice_repeat_minutes' => 3,
            'voice_max_attempts' => 5,
            'voice_all_after_minutes' => 12,
            'unmanaged_after_minutes' => 15,
        ]);
    }

    private function openIncident(int $ageMinutes, array $attrs = []): Incident
    {
        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create(array_merge([
            'callout_id' => $callout->id,
            'status' => 'open',
        ], $attrs));
        $incident->created_at = now()->subMinutes($ageMinutes);
        $incident->save();

        return $incident;
    }

    public function test_places_voice_call_for_unacknowledged_incident_after_delay()
    {
        $this->mock(VoiceCaller::class, fn ($m) => $m->shouldReceive('call')->once()->andReturn('CA1'));

        User::factory()->dutyOfficer()->create(['phone' => '+447111111111']);
        $incident = $this->openIncident(5);

        $this->artisan('callouts:check-overdue')->assertExitCode(0);

        $incident->refresh();
        $this->assertEquals(1, $incident->voice_call_count);
        $this->assertNotNull($incident->last_voice_call_at);
        $this->assertDatabaseHas('incident_notes', [
            'incident_id' => $incident->id,
            'content' => 'SYSTEM: Voice-call escalation attempt 1 — dialled 1 duty officer(s) (1 placed). Press 1 on the call to acknowledge.',
        ]);
    }

    public function test_does_not_call_before_the_delay()
    {
        $this->mock(VoiceCaller::class, fn ($m) => $m->shouldReceive('call')->never());

        User::factory()->dutyOfficer()->create(['phone' => '+447111111111']);
        $incident = $this->openIncident(1); // younger than voice_after_minutes

        $this->artisan('callouts:check-overdue')->assertExitCode(0);

        $this->assertEquals(0, $incident->fresh()->voice_call_count);
    }

    public function test_does_not_call_an_acknowledged_incident()
    {
        $this->mock(VoiceCaller::class, fn ($m) => $m->shouldReceive('call')->never());

        $controller = User::factory()->dutyOfficer()->create(['phone' => '+447222222222']);
        $incident = $this->openIncident(10, [
            'status' => 'managed',
            'incident_controller_id' => $controller->id,
            'acknowledged_at' => now(),
        ]);

        $this->artisan('callouts:check-overdue')->assertExitCode(0);

        $this->assertEquals(0, $incident->fresh()->voice_call_count);
    }

    public function test_respects_the_repeat_interval()
    {
        $this->mock(VoiceCaller::class, fn ($m) => $m->shouldReceive('call')->never());

        User::factory()->dutyOfficer()->create(['phone' => '+447111111111']);
        $incident = $this->openIncident(30, [
            'voice_call_count' => 1,
            'last_voice_call_at' => now()->subMinute(), // 1 min ago < repeat interval (3)
        ]);

        $this->artisan('callouts:check-overdue')->assertExitCode(0);

        $this->assertEquals(1, $incident->fresh()->voice_call_count);
    }

    public function test_stops_after_max_attempts()
    {
        $this->mock(VoiceCaller::class, fn ($m) => $m->shouldReceive('call')->never());

        User::factory()->dutyOfficer()->create(['phone' => '+447111111111']);
        $incident = $this->openIncident(60, [
            'voice_call_count' => 5, // == max
            'last_voice_call_at' => now()->subMinutes(10),
        ]);

        $this->artisan('callouts:check-overdue')->assertExitCode(0);

        $this->assertEquals(5, $incident->fresh()->voice_call_count);
    }

    public function test_calls_only_the_on_call_do_before_escalation()
    {
        // Before the 15-minute escalation, the on-call DO gets the calls to themselves —
        // it must NOT widen to other duty officers yet.
        $this->mock(VoiceCaller::class, function ($m) {
            $m->shouldReceive('call')->once()
                ->withArgs(fn ($to, $url) => $to === '+447111111111')
                ->andReturn('CA1');
        });

        $onCall = User::factory()->dutyOfficer()->create(['phone' => '+447111111111']);
        OnCallShift::create([
            'user_id' => $onCall->id,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);
        // Another duty officer who is NOT on call — must not be called yet.
        User::factory()->dutyOfficer()->create(['phone' => '+447999999999']);

        $this->openIncident(5); // unacknowledged, not yet escalated

        $this->artisan('callouts:check-overdue')->assertExitCode(0);
    }

    public function test_calls_widen_to_all_dos_after_escalation()
    {
        // Once escalated (15-minute mark passed unmanaged), calls widen to ALL duty officers.
        $this->mock(VoiceCaller::class, fn ($m) => $m->shouldReceive('call')->twice()->andReturn('CA1'));

        User::factory()->dutyOfficer()->create(['phone' => '+447111111111']);
        User::factory()->dutyOfficer()->create(['phone' => '+447222222222']);

        $this->openIncident(20, [
            'escalated_at' => now()->subMinute(),
            'voice_call_count' => 1,
            'last_voice_call_at' => now()->subMinutes(5),
        ]);

        $this->artisan('callouts:check-overdue')->assertExitCode(0);
    }

    public function test_voice_calls_widen_to_all_dos_at_the_voice_all_threshold_before_full_escalation()
    {
        // From voice_all_after_minutes (12) the calls widen to ALL duty officers — even
        // though the incident has not yet reached the 15-minute unmanaged escalation.
        $this->mock(VoiceCaller::class, fn ($m) => $m->shouldReceive('call')->twice()->andReturn('CA1'));

        $onCall = User::factory()->dutyOfficer()->create(['phone' => '+447111111111']);
        OnCallShift::create([
            'user_id' => $onCall->id,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);
        // A second duty officer who is NOT on call — should now also be called.
        User::factory()->dutyOfficer()->create(['phone' => '+447222222222']);

        // 13 minutes old (>= 12), not yet escalated, and due another call.
        $this->openIncident(13, [
            'voice_call_count' => 3,
            'last_voice_call_at' => now()->subMinutes(5),
        ]);

        $this->artisan('callouts:check-overdue')->assertExitCode(0);
    }

    public function test_voice_escalation_can_be_disabled()
    {
        Config::set('callouts.escalation.voice_max_attempts', 0);
        $this->mock(VoiceCaller::class, fn ($m) => $m->shouldReceive('call')->never());

        User::factory()->dutyOfficer()->create(['phone' => '+447111111111']);
        $incident = $this->openIncident(10);

        $this->artisan('callouts:check-overdue')->assertExitCode(0);

        $this->assertEquals(0, $incident->fresh()->voice_call_count);
    }
}
