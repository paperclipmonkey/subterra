<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\CalloutCreated;
use App\Mail\CalloutOpenedDONotification;
use App\Models\Callout;
use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalloutOpenedDONotificationTest extends TestCase
{
    use RefreshDatabase;

    private function shift(User $do, Carbon $start, Carbon $end, bool $notify): OnCallShift
    {
        return OnCallShift::create([
            'user_id' => $do->id,
            'start_at' => $start,
            'end_at' => $end,
            'notify_do' => $notify,
        ]);
    }

    #[Test]
    public function it_emails_only_the_on_call_duty_officer_with_notifications_enabled(): void
    {
        Mail::fake();
        Carbon::setTestNow('2025-06-01 12:00:00');

        $onCall = User::factory()->dutyOfficer()->create(['email' => 'oncall@example.com']);
        $this->shift($onCall, now()->subHour(), now()->addHour(), true);

        $later = User::factory()->dutyOfficer()->create(['email' => 'later@example.com']);
        $this->shift($later, now()->addHours(2), now()->addHours(6), true);

        $optedOut = User::factory()->dutyOfficer()->create(['email' => 'optout@example.com']);
        $this->shift($optedOut, now()->subHour(), now()->addHour(), false);

        $callout = Callout::factory()->create();
        event(new CalloutCreated($callout));

        Mail::assertSent(CalloutOpenedDONotification::class, 1);
        Mail::assertSent(
            CalloutOpenedDONotification::class,
            fn ($mail) => $mail->hasTo('oncall@example.com') && $mail->callout->is($callout)
        );
    }

    #[Test]
    public function it_sends_nothing_when_the_on_call_shift_has_notifications_disabled(): void
    {
        Mail::fake();

        $do = User::factory()->dutyOfficer()->create();
        $this->shift($do, now()->subHour(), now()->addHour(), false);

        event(new CalloutCreated(Callout::factory()->create()));

        Mail::assertNotSent(CalloutOpenedDONotification::class);
    }

    #[Test]
    public function the_email_renders(): void
    {
        $callout = Callout::factory()->create();

        $html = (new CalloutOpenedDONotification($callout))->render();

        $this->assertStringContainsString($callout->user->name, $html);
    }
}
