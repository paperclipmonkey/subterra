<?php

namespace Tests\Feature;

use App\Console\Commands\CheckOverdueCallouts;
use App\Models\Callout;
use App\Models\Cave;
use App\Models\Incident;
use App\Models\OnCallShift;
use App\Models\User;
use App\Notifications\CalloutImminentNotification;
use App\Notifications\IncidentEscalatedNotification;
use App\Notifications\OverdueCalloutNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckOverdueCalloutsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure we have a cave for callouts
        Cave::factory()->create();
    }

    public function test_warns_current_duty_officer_when_callout_is_imminent()
    {
        Notification::fake();
        Carbon::setTestNow('2025-01-01 12:00:00');

        // user is the DO
        $do = User::factory()->create(['is_admin' => true, 'name' => 'Duty Officer']);
        
        // Setup shift covering now+15m
        // Shift is 09:00 to 17:00
        OnCallShift::create([
            'user_id' => $do->id,
            'start_at' => now()->startOfDay(),
            'end_at' => now()->endOfDay(),
        ]);

        // Callout due at 12:15 (imminent window is now+15m)
        // Logic checks [now+15, now+16)
        // If due at 12:15:00, 15m before is 12:00:00.
        $callout = Callout::factory()->create([
            'callout_time' => now()->addMinutes(15), 
            'status' => 'active'
        ]);

        // Verify Contact also notified
        // Create a participant
        $participant = \App\Models\CalloutParticipant::create([
            'callout_id' => $callout->id,
            'name' => 'Participant 1',
            'phone' => '+447999999999',
        ]);
        
        $this->artisan('callouts:check-overdue')
             ->assertExitCode(0);

        Notification::assertSentTo(
            [$do], 
            CalloutImminentNotification::class,
            function ($notification, $channels) use ($callout) {
                return $notification->callout->id === $callout->id;
            }
        );

        Notification::assertSentTo(
             [$participant],
             \App\Notifications\CalloutImminentContactNotification::class
        );
    }

    public function test_does_not_warn_if_callout_is_not_in_imminent_window()
    {
        Notification::fake();
        Carbon::setTestNow('2025-01-01 12:00:00');

        $do = User::factory()->create(['is_admin' => true]);
        OnCallShift::create([
            'user_id' => $do->id,
            'start_at' => now()->startOfDay(),
            'end_at' => now()->endOfDay(),
        ]);

        // Callout due at 13:00 (too far)
        Callout::factory()->create([
            'callout_time' => now()->addHour(), 
            'status' => 'active'
        ]);

        // Callout due at 12:05 (too close, likely already warned or missed)
        // Although the command only checks specific window.
        Callout::factory()->create([
            'callout_time' => now()->addMinutes(5), 
            'status' => 'active'
        ]);

        $this->artisan('callouts:check-overdue');

        Notification::assertNothingSent();
    }

    public function test_warns_all_admins_if_imminent_and_no_shift_coverage()
    {
        Notification::fake();
        Carbon::setTestNow('2025-01-01 12:00:00');

        $admin1 = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $admin2 = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        // NO SHIFT CREATED

        $callout = Callout::factory()->create([
            'callout_time' => now()->addMinutes(15),
            'status' => 'active'
        ]);

        $this->artisan('callouts:check-overdue');

        Notification::assertSentTo(
            [$admin1, $admin2], 
            CalloutImminentNotification::class
        );

        Notification::assertNotSentTo(
            [$user], 
            CalloutImminentNotification::class
        );
    }

    public function test_escalates_incident_if_unclaimed_for_15_minutes()
    {
        Notification::fake();
        Carbon::setTestNow('2025-01-01 12:30:00');

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        // Incident triggered at 12:00 (30 mins ago), so > 15m.
        // No controller.
        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create([
            'callout_id' => $callout->id,
            'status' => 'open',
        ]);
        
        // Force update created_at to simulate old incident
        $incident->created_at = now()->subMinutes(30);
        $incident->save();
        
        $this->artisan('callouts:check-overdue');

        Notification::assertSentTo(
            [$admin],
            \App\Notifications\UnmanagedIncidentNotification::class
        );

        // Assert System Note added
        $this->assertDatabaseHas('incident_notes', [
            'incident_id' => $incident->id,
            'user_id' => null,
            'content' => 'SYSTEM ALERT: Incident ESCALATED. Notification sent to all Duty Officers due to 15m idle time.'
        ]);
    }

    public function test_does_not_escalate_if_incident_has_controller()
    {
        Notification::fake();
        Carbon::setTestNow('2025-01-01 12:30:00');

        $admin = User::factory()->create(['is_admin' => true]);
        $controller = User::factory()->create();

        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create([
            'callout_id' => $callout->id,
            'status' => 'open',
            'created_at' => now()->subMinutes(30),
            'incident_controller_id' => $controller->id // Has controller
        ]);

        $this->artisan('callouts:check-overdue');

        Notification::assertNotSentTo([$admin], \App\Notifications\UnmanagedIncidentNotification::class);
    }

    public function test_does_not_escalate_if_incident_is_fresh()
    {
        Notification::fake();
        Carbon::setTestNow('2025-01-01 12:30:00');

        $admin = User::factory()->create(['is_admin' => true]);

        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create([
            'callout_id' => $callout->id,
            'status' => 'open',
            'created_at' => now()->subMinutes(5), // Only 5 mins old
        ]);

        $this->artisan('callouts:check-overdue');

        Notification::assertNotSentTo([$admin], \App\Notifications\UnmanagedIncidentNotification::class);
    }

    public function test_does_not_duplicate_escalation_if_already_escalated()
    {
        Notification::fake();
        Carbon::setTestNow('2025-01-01 12:30:00');

        $admin = User::factory()->create(['is_admin' => true]);

        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create([
            'callout_id' => $callout->id,
            'status' => 'open',
            'created_at' => now()->subMinutes(60), // Very old
        ]);

        // Add the system note manually
        $incident->notes()->create([
            'user_id' => null,
            'content' => 'SYSTEM ALERT: Incident ESCALATED. Notification sent to all Duty Officers due to 15m idle time.'
        ]);

        $this->artisan('callouts:check-overdue');

        Notification::assertNotSentTo([$admin], \App\Notifications\UnmanagedIncidentNotification::class);
    }

    public function test_triggers_overdue_callouts_and_notifies_admins()
    {
        // Regression test for the original functionality
        Notification::fake();
        Carbon::setTestNow('2025-01-01 12:00:00');

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        
        $callout = Callout::factory()->create([
            'callout_time' => now()->subMinute(), // Due 1 min ago
            'status' => 'active'
        ]);

        $this->artisan('callouts:check-overdue');

        $this->assertDatabaseHas('callouts', ['id' => $callout->id, 'status' => 'triggered']);
        $this->assertDatabaseHas('incidents', ['callout_id' => $callout->id, 'status' => 'open']);

        Notification::assertSentTo(
            [$admin],
            OverdueCalloutNotification::class
        );
    }
}
