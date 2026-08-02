<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\CalloutParticipant;
use App\Models\User;
use App\Notifications\CalloutImminentContactNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CalloutParticipantNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_be_notified_without_exception()
    {
        Notification::fake();

        $user = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $user->id]);

        $participant = CalloutParticipant::create([
            'callout_id' => $callout->id,
            'user_id' => null,
            'name' => 'John Doe',
            'phone' => '+1234567890',
            'email' => 'john@example.com',
        ]);

        // This would throw BadMethodCallException if Notifiable was missing
        $participant->notify(new CalloutImminentContactNotification($callout));

        Notification::assertSentTo(
            [$participant],
            CalloutImminentContactNotification::class
        );
    }

    public function test_notification_routing_falls_back_to_linked_user_contact_details()
    {
        // Regression (L5): participants added via autocomplete may only carry a user_id.
        // Mail/SMS routing must fall back to the linked account rather than silently
        // dropping an overdue-callout warning.
        $linkedUser = User::factory()->create([
            'email' => 'linked@example.com',
            'phone' => '+447700900555',
        ]);
        $callout = Callout::factory()->create();

        $participant = CalloutParticipant::create([
            'callout_id' => $callout->id,
            'user_id' => $linkedUser->id,
            'name' => 'Autocomplete Person',
            'phone' => null,
            'email' => null,
        ]);

        $this->assertSame('linked@example.com', $participant->routeNotificationForMail());
        $this->assertSame('+447700900555', $participant->routeNotificationForSms());
    }

    public function test_notification_routing_prefers_ad_hoc_contact_details()
    {
        $linkedUser = User::factory()->create([
            'email' => 'linked@example.com',
            'phone' => '+447700900555',
        ]);
        $callout = Callout::factory()->create();

        $participant = CalloutParticipant::create([
            'callout_id' => $callout->id,
            'user_id' => $linkedUser->id,
            'name' => 'Ad-hoc Person',
            'phone' => '+447700900666',
            'email' => 'adhoc@example.com',
        ]);

        $this->assertSame('adhoc@example.com', $participant->routeNotificationForMail());
        $this->assertSame('+447700900666', $participant->routeNotificationForSms());
    }
}
