<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\SlackAlerts\Facades\SlackAlert;
use Tests\TestCase;

class UserSignupSlackNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_magic_link_signup_sends_slack_notification()
    {
        SlackAlert::fake();
        config()->set('slack-alerts.webhook_urls.signups', 'https://hooks.slack.com/services/test/signups');

        $email = 'magic@example.com';

        $response = $this->postJson('/api/auth/magic-link', [
            'email' => $email,
            'agreed_to_tos' => true
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['email' => $email]);

        SlackAlert::expectMessagesSent(function ($message) use ($email) {
            return ($message['webhookUrl'] ?? '') === 'https://hooks.slack.com/services/test/signups' &&
                   str_contains($message['text'] ?? '', 'A new user has signed up') &&
                   str_contains($message['text'] ?? '', $email);
        });
    }

    public function test_user_creation_via_api_sends_slack_notification()
    {
        SlackAlert::fake();
        config()->set('slack-alerts.webhook_urls.signups', 'https://hooks.slack.com/services/test/signups');

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        
        $email = 'api_created@example.com';

        // Note: The route in api.php is Route::post('/users', ...) inside ApiIsAuthenticated group
        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'API Created User',
            'email' => $email
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => $email]);

        SlackAlert::expectMessagesSent(function ($message) use ($email) {
            return ($message['webhookUrl'] ?? '') === 'https://hooks.slack.com/services/test/signups' &&
                   str_contains($message['text'] ?? '', 'A new user has signed up') &&
                   str_contains($message['text'] ?? '', 'API Created User') &&
                   str_contains($message['text'] ?? '', $email);
        });
    }
}
