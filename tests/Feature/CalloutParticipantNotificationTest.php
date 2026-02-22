<?php

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
}
