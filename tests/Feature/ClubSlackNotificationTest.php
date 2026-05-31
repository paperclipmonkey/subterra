<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ClubAccessRequested;
use App\Events\ClubAccessResponded;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\SlackAlerts\Facades\SlackAlert;
use Tests\TestCase;

class ClubSlackNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_access_requested_sends_slack_notification()
    {
        SlackAlert::fake();
        // The URL doesn't strictly matter for faking the channel routing,
        // but we'll set it just in case some logic depends on it.
        config()->set('slack-alerts.webhook_urls.approvals', 'https://hooks.slack.com/services/test/approvals');

        $user = User::factory()->create();
        $club = Club::factory()->create();

        // Dispatch the event
        event(new ClubAccessRequested($club, $user));

        SlackAlert::expectMessagesSent(function ($message) use ($user, $club) {
            $expectedUrl = url('/club/'.$club->slug).'?editClub=1&tab=pending';

            // Check the message is sent to the correctly mapped channel URL if defined,
            // but primarily check the message content has the right text.
            return str_contains($message['text'] ?? '', 'NEW CLUB APPLICATION') &&
                   str_contains($message['text'] ?? '', $user->name) &&
                   str_contains($message['text'] ?? '', $user->email) &&
                   str_contains($message['text'] ?? '', $club->name) &&
                   str_contains($message['text'] ?? '', "<$expectedUrl|Review Request>");
        });
    }

    public function test_club_access_responded_approved_sends_slack_notification()
    {
        SlackAlert::fake();
        config()->set('slack-alerts.webhook_urls.approvals', 'https://hooks.slack.com/services/test/approvals');

        $user = User::factory()->create();
        $club = Club::factory()->create();

        // Dispatch the approved event
        event(new ClubAccessResponded($club, $user, 'approved'));

        SlackAlert::expectMessagesSent(function ($message) use ($user, $club) {
            return str_contains($message['text'] ?? '', 'CLUB MEMBERSHIP APPROVED') &&
                   str_contains($message['text'] ?? '', $user->name) &&
                   str_contains($message['text'] ?? '', $user->email) &&
                   str_contains($message['text'] ?? '', $club->name);
        });
    }

    public function test_club_access_responded_rejected_does_not_send_slack_notification()
    {
        SlackAlert::fake();
        config()->set('slack-alerts.webhook_urls.approvals', 'https://hooks.slack.com/services/test/approvals');

        $user = User::factory()->create();
        $club = Club::factory()->create();

        // Dispatch a rejected event
        event(new ClubAccessResponded($club, $user, 'rejected'));

        SlackAlert::expectNoMessagesSent();
    }
}
