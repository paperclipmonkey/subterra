<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\TripCreated;
use App\Mail\TripStartedDONotification;
use App\Models\Cave;
use App\Models\OnCallShift;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The "email me when a trip is started during this shift" feature: when a trip is created
 * with a start time covered by an on-call shift that has notifications enabled, ONLY that
 * shift's duty officer is emailed.
 */
class TripStartedDONotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrip(?Carbon $startTime): Trip
    {
        $entrance = Cave::factory()->create();

        return Trip::factory()->create([
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'start_time' => $startTime,
        ]);
    }

    private function shift(User $do, Carbon $start, Carbon $end, bool $notify): OnCallShift
    {
        return OnCallShift::create([
            'user_id' => $do->id,
            'start_at' => $start,
            'end_at' => $end,
            'notify_do' => $notify,
        ]);
    }

    public function test_emails_the_on_call_duty_officer_when_a_trip_starts_during_their_shift()
    {
        Mail::fake();
        Carbon::setTestNow('2025-06-01 12:00:00');

        $do = User::factory()->dutyOfficer()->create(['email' => 'oncall@example.com']);
        $this->shift($do, now()->subHour(), now()->addHour(), true);

        $trip = $this->makeTrip(now());
        event(new TripCreated($trip, User::factory()->create()));

        Mail::assertSent(TripStartedDONotification::class, 1);
        Mail::assertSent(
            TripStartedDONotification::class,
            fn ($mail) => $mail->hasTo('oncall@example.com') && $mail->trip->is($trip)
        );
    }

    public function test_emails_only_the_on_call_do_not_other_duty_officers()
    {
        Mail::fake();
        Carbon::setTestNow('2025-06-01 12:00:00');

        // On call now, notifications ON — should be emailed.
        $onCall = User::factory()->dutyOfficer()->create(['email' => 'oncall@example.com']);
        $this->shift($onCall, now()->subHour(), now()->addHour(), true);

        // Shift later today (does not cover now) — must NOT be emailed.
        $later = User::factory()->dutyOfficer()->create(['email' => 'later@example.com']);
        $this->shift($later, now()->addHours(2), now()->addHours(6), true);

        // Covering now but notifications OFF — must NOT be emailed.
        $optedOut = User::factory()->dutyOfficer()->create(['email' => 'optout@example.com']);
        $this->shift($optedOut, now()->subHour(), now()->addHour(), false);

        event(new TripCreated($this->makeTrip(now()), User::factory()->create()));

        Mail::assertSent(TripStartedDONotification::class, 1);
        Mail::assertSent(TripStartedDONotification::class, fn ($mail) => $mail->hasTo('oncall@example.com'));
        Mail::assertNotSent(
            TripStartedDONotification::class,
            fn ($mail) => $mail->hasTo('later@example.com') || $mail->hasTo('optout@example.com')
        );
    }

    public function test_no_email_when_the_covering_shift_has_notifications_disabled()
    {
        Mail::fake();
        Carbon::setTestNow('2025-06-01 12:00:00');

        $do = User::factory()->dutyOfficer()->create();
        $this->shift($do, now()->subHour(), now()->addHour(), false);

        event(new TripCreated($this->makeTrip(now()), User::factory()->create()));

        Mail::assertNotSent(TripStartedDONotification::class);
    }

    public function test_no_email_when_no_shift_covers_the_trip_start()
    {
        Mail::fake();
        Carbon::setTestNow('2025-06-01 12:00:00');

        $do = User::factory()->dutyOfficer()->create();
        $this->shift($do, now()->addHours(3), now()->addHours(6), true); // future shift

        event(new TripCreated($this->makeTrip(now()), User::factory()->create()));

        Mail::assertNotSent(TripStartedDONotification::class);
    }

    public function test_no_email_when_trip_has_no_start_time()
    {
        Mail::fake();
        Carbon::setTestNow('2025-06-01 12:00:00');

        $do = User::factory()->dutyOfficer()->create();
        $this->shift($do, now()->subHour(), now()->addHour(), true);

        $trip = Trip::factory()->make(['start_time' => null]);
        event(new TripCreated($trip, User::factory()->create()));

        Mail::assertNotSent(TripStartedDONotification::class);
    }
}
