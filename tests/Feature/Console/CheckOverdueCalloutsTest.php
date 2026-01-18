<?php

namespace Tests\Feature\Console;

use App\Models\Callout;
use App\Models\Cave;
use App\Models\Incident;
use App\Models\User;
use App\Notifications\OverdueCalloutNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckOverdueCalloutsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_triggers_overdue_callouts_and_creates_incident()
    {
        Notification::fake();

        // Create Admin
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        
        // Create Cave
        $cave = Cave::factory()->create();

        // Create Overdue Callout
        $overdueCallout = Callout::factory()->create([
            'cave_id' => $cave->id,
            'callout_time' => now()->subMinute(), // Overdue
            'status' => 'active',
        ]);

        // Create Active (Not Overdue) Callout
        $activeCallout = Callout::factory()->create([
            'cave_id' => $cave->id,
            'callout_time' => now()->addHour(), // Not overdue
            'status' => 'active',
        ]);

        // Create Already Triggered Callout
        $triggeredCallout = Callout::factory()->create([
            'cave_id' => $cave->id,
            'callout_time' => now()->subHour(),
            'status' => 'triggered',
        ]);

        // Run Command
        $this->artisan('callouts:check-overdue')
             ->assertExitCode(0);

        // Assert Overdue Callout Triggered
        $this->assertEquals('triggered', $overdueCallout->fresh()->status);
        
        // Assert Incident Created
        $this->assertDatabaseHas('incidents', [
            'callout_id' => $overdueCallout->id,
            'status' => 'open',
        ]);

        // Assert Notification Sent
        Notification::assertSentTo(
            [$admin],
            OverdueCalloutNotification::class,
            function ($notification, $channels) use ($overdueCallout) {
                return $notification->callout->id === $overdueCallout->id;
            }
        );

        // Assert Others Unchanged
        $this->assertEquals('active', $activeCallout->fresh()->status);
        $this->assertDatabaseMissing('incidents', ['callout_id' => $activeCallout->id]);
        
        // Ensure we didn't duplicate incident for already triggered callout (though command filters by active)
        $this->assertEquals('triggered', $triggeredCallout->fresh()->status);
    }
}
