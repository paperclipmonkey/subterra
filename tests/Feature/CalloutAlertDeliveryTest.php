<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\Cave;
use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Safety alerts must not depend on the queue worker: production runs one
 * low-priority worker shared with long image/registry jobs, so a queued overdue
 * alert could be delayed by minutes, or never sent if the worker is down.
 */
class CalloutAlertDeliveryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function overdue_and_imminent_alerts_are_sent_without_the_queue(): void
    {
        Carbon::setTestNow('2025-01-01 12:00:00');
        Queue::fake();
        Http::fake();

        $do = User::factory()->dutyOfficer()->create();
        OnCallShift::create(['user_id' => $do->id, 'start_at' => now()->startOfDay(), 'end_at' => now()->endOfDay()]);

        $cave = Cave::factory()->create();
        $overdue = Callout::factory()->create(['cave_id' => $cave->id, 'status' => 'active', 'callout_time' => now()->subMinute()]);
        Callout::factory()->create(['cave_id' => $cave->id, 'status' => 'active', 'callout_time' => now()->addMinutes(15)]);

        $this->artisan('callouts:check-overdue')->assertExitCode(0);

        $this->assertSame('triggered', $overdue->fresh()->status);
        Queue::assertNotPushed(SendQueuedNotifications::class);
        // Delivered in-process: the duty officer's emails reached the (array) mailer.
        $recipients = collect(app('mailer')->getSymfonyTransport()->messages())
            ->flatMap(fn ($sent) => $sent->getEnvelope()->getRecipients())
            ->map(fn ($address) => $address->getAddress());
        $this->assertContains($do->email, $recipients);
    }
}
