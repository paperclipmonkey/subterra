<?php

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\Cave;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\SlackAlerts\Facades\SlackAlert;
use Tests\TestCase;

class SlackNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure we have a cave for callouts
        Cave::factory()->create();
    }

    public function test_new_callout_sends_slack_notification_to_open_channel()
    {
        SlackAlert::fake();
        // Mock Env
        config()->set('slack-alerts.webhook_urls.callouts-open', 'https://hooks.slack.com/services/test/open');

        $user = User::factory()->withApprovedClub()->create();
        $cave = Cave::first();

        // Setup Shift
        \App\Models\OnCallShift::create([
            'user_id' => $user->id,
            'start_at' => now()->startOfDay(),
            'end_at' => now()->addDays(1)->endOfDay(),
        ]);

        $calloutData = [
            'cave_id' => $cave->id,
            'callout_time' => now()->addHours(2)->toDateTimeString(),
            'trip_plan' => 'Exploring the deep',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Layby',
            'participants' => [
                ['name' => 'John Doe'],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/callouts', $calloutData);

        if ($response->status() !== 201) {
            dump($response->json());
        }
        $response->assertStatus(201);

        SlackAlert::expectMessagesSent(function ($message) use ($cave) {
            return ($message['webhookUrl'] ?? '') === 'https://hooks.slack.com/services/test/open' &&
                   str_contains($message['text'] ?? '', 'New Callout') &&
                   str_contains($message['text'] ?? '', 'Party of 2') &&
                   str_contains($message['text'] ?? '', $cave->name);
        });
    }

    public function test_new_callout_without_creator_in_participants_sends_correct_count()
    {
        SlackAlert::fake();
        config()->set('slack-alerts.webhook_urls.callouts-open', 'https://hooks.slack.com/services/test/open');

        $user = User::factory()->withApprovedClub()->create();
        $cave = Cave::first();

        // Setup Shift
        \App\Models\OnCallShift::create([
            'user_id' => $user->id,
            'start_at' => now()->startOfDay(),
            'end_at' => now()->addDays(1)->endOfDay(),
        ]);

        $calloutData = [
            'cave_id' => $cave->id,
            'callout_time' => now()->addHours(2)->toDateTimeString(),
            'trip_plan' => 'Solo exploration',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Layby',
            'participants' => [
                ['name' => 'John Guest'], // Creator is NOT here
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/callouts', $calloutData);
        $response->assertStatus(201);

        SlackAlert::expectMessagesSent(function ($message) use ($cave) {
            return ($message['webhookUrl'] ?? '') === 'https://hooks.slack.com/services/test/open' &&
                   str_contains($message['text'] ?? '', 'New Callout') &&
                   str_contains($message['text'] ?? '', 'Party of 2') &&
                   str_contains($message['text'] ?? '', $cave->name);
        });
    }

    public function test_new_callout_with_creator_in_participants_sends_correct_count()
    {
        SlackAlert::fake();
        config()->set('slack-alerts.webhook_urls.callouts-open', 'https://hooks.slack.com/services/test/open');

        $user = User::factory()->withApprovedClub()->create();
        $cave = Cave::first();

        // Setup Shift
        \App\Models\OnCallShift::create([
            'user_id' => $user->id,
            'start_at' => now()->startOfDay(),
            'end_at' => now()->addDays(1)->endOfDay(),
        ]);

        $calloutData = [
            'cave_id' => $cave->id,
            'callout_time' => now()->addHours(2)->toDateTimeString(),
            'trip_plan' => 'Trip with friends',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Layby',
            'participants' => [
                ['name' => $user->name, 'user_id' => $user->id],
                ['name' => 'Friend 1'],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/callouts', $calloutData);
        $response->assertStatus(201);

        SlackAlert::expectMessagesSent(function ($message) use ($cave) {
            return ($message['webhookUrl'] ?? '') === 'https://hooks.slack.com/services/test/open' &&
                   str_contains($message['text'] ?? '', 'New Callout') &&
                   str_contains($message['text'] ?? '', 'Party of 2') &&
                   str_contains($message['text'] ?? '', $cave->name);
        });
    }

    public function test_overdue_callout_trigger_sends_slack_notification_to_overdue_channel()
    {
        SlackAlert::fake();
        config()->set('slack-alerts.webhook_urls.callouts-overdue', 'https://hooks.slack.com/services/test/overdue');
        Carbon::setTestNow('2025-01-01 12:00:00');

        $user = User::factory()->admin()->create(['is_active' => true]);
        $cave = Cave::first();

        $callout = Callout::factory()->create([
            'cave_id' => $cave->id,
            'callout_time' => now()->subMinute(), // Due 1 min ago
            'status' => 'active',
        ]);

        $this->artisan('callouts:check-overdue');

        $incident = \App\Models\Incident::where('callout_id', $callout->id)->first();

        SlackAlert::expectMessagesSent(function ($message) use ($incident) {
            return ($message['webhookUrl'] ?? '') === 'https://hooks.slack.com/services/test/overdue' &&
                   str_contains($message['text'] ?? '', 'OVERDUE') &&
                   str_contains($message['text'] ?? '', '<!channel>') &&
                   str_contains($message['text'] ?? '', $incident->id);
        });
    }

    public function test_incident_update_sends_slack_notification()
    {
        SlackAlert::fake();
        config()->set('slack-alerts.webhook_urls.callouts-overdue', 'https://hooks.slack.com/services/test/overdue');

        $user = User::factory()->admin()->create(['is_active' => true]);

        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create([
            'callout_id' => $callout->id,
            'status' => 'open',
        ]);

        // Act: Update Incident Status to Resolved
        $incident->update(['status' => 'resolved']);

        SlackAlert::expectMessagesSent(function ($message) use ($incident) {
            return ($message['webhookUrl'] ?? '') === 'https://hooks.slack.com/services/test/overdue' &&
                   str_contains($message['text'] ?? '', 'Incident #'.$incident->id) &&
                   str_contains($message['text'] ?? '', 'resolved');
        });
    }

    public function test_incident_note_creation_sends_slack_notification()
    {
        SlackAlert::fake();
        config()->set('slack-alerts.webhook_urls.callouts-overdue', 'https://hooks.slack.com/services/test/overdue');

        $user = User::factory()->admin()->create(['name' => 'Admin User']);
        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create([
            'callout_id' => $callout->id,
            'status' => 'open',
        ]);

        // Act: Create new note
        $incident->notes()->create([
            'user_id' => $user->id,
            'content' => 'Team is deploying now.',
        ]);

        SlackAlert::expectMessagesSent(function ($message) {
            return ($message['webhookUrl'] ?? '') === 'https://hooks.slack.com/services/test/overdue' &&
                   str_contains($message['text'] ?? '', 'New Update') &&
                   str_contains($message['text'] ?? '', 'Team is deploying now');
        });
    }
}
