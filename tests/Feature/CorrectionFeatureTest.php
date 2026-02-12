<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\SlackAlerts\Facades\SlackAlert;
use Tests\TestCase;

class CorrectionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_submit_correction_via_slack()
    {
        // Mock SlackAlert
        SlackAlert::fake();

        $user = User::factory()->withApprovedClub()->create();

        $response = $this->actingAs($user)->postJson('/api/corrections', [
            'correction' => 'This is a test correction with sufficient length.',
            'entity_type' => 'cave',
            'entity_id' => 123,
            'entity_name' => 'Test Cave',
            'url' => 'https://example.com/caves/test-cave',
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Correction submitted successfully']);

        SlackAlert::expectMessagesSent(function ($message) use ($user) {
            // Note: Spatie Fake doesn't easily expose the 'to' channel in the message array
            // depending on version, but we can check the text.
            // If needed we can check $message['webhookUrlName'] if available.

            $text = $message['text'] ?? '';

            return str_contains($text, 'Factual Correction Submitted') &&
                   str_contains($text, $user->name) &&
                   str_contains($text, 'Test Cave') &&
                   str_contains($text, 'This is a test correction with sufficient length.');
        });
    }

    public function test_submission_requires_validation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/corrections', [
            'correction' => 'Short', // Too short
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['correction', 'entity_type', 'entity_id', 'entity_name', 'url']);
    }

    public function test_guest_cannot_submit_correction()
    {
        $response = $this->postJson('/api/corrections', [
             'correction' => 'Valid correction text here.',
        ]);

        $response->assertStatus(401);
    }
}
