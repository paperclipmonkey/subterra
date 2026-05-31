<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\CalloutParticipant;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ClickSendWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_cancels_callout_with_out_safe_message()
    {
        // 1. Setup Active Callout
        $user = User::factory()->create();
        $callout = Callout::factory()->create([
            'status' => 'active',
            'user_id' => $user->id,
            'callout_time' => now()->addMinutes(30),
        ]);

        // Add participant with known phone
        $participant = CalloutParticipant::create([
            'callout_id' => $callout->id,
            'name' => 'Test Participant',
            'phone' => '+447777777777',
        ]);

        Config::set('services.clicksend.webhook_secret', 'secret-key');

        // 2. Simulate Webhook Request
        $response = $this->postJson('/api/webhooks/clicksend/sms?secret=secret-key', [
            'from' => '+447777777777',
            'body' => 'OUT SAFE',
        ]);

        $response->assertStatus(200);

        // 3. Assert Callout Cancelled
        $this->assertDatabaseHas('callouts', [
            'id' => $callout->id,
            'status' => 'cancelled',
            'cancelled_location' => 'SMS',
        ]);
    }

    public function test_webhook_logs_generic_message_to_incident()
    {
        // 1. Setup Triggered Callout & Incident
        $user = User::factory()->create();
        $callout = Callout::factory()->create([
            'status' => 'triggered',
            'user_id' => $user->id,
        ]);

        $participant = CalloutParticipant::create([
            'callout_id' => $callout->id,
            'name' => 'Test Participant',
            'phone' => '+447777777777',
        ]);

        $incident = Incident::create(['callout_id' => $callout->id, 'status' => 'open']);

        Config::set('services.clicksend.webhook_secret', 'secret-key');

        // 2. Simulate Webhook Request
        $response = $this->postJson('/api/webhooks/clicksend/sms?secret=secret-key', [
            'from' => '+447777777777',
            'body' => 'We are delayed but okay',
        ]);

        $response->assertStatus(200);

        // 3. Assert Note Created
        $this->assertDatabaseHas('incident_notes', [
            'incident_id' => $incident->id,
            'content' => 'SMS Received from +447777777777: We are delayed but okay',
        ]);
    }

    public function test_webhook_logs_generic_message_to_team_details_if_no_incident()
    {
        // 1. Setup Active Callout (No Incident)
        $user = User::factory()->create();
        $callout = Callout::factory()->create([
            'status' => 'active',
            'user_id' => $user->id,
            'team_details' => 'Initial Team Info',
        ]);

        $participant = CalloutParticipant::create([
            'callout_id' => $callout->id,
            'name' => 'Test Participant',
            'phone' => '+447777777777',
        ]);

        Config::set('services.clicksend.webhook_secret', 'secret-key');

        // 2. Simulate Webhook Request
        $response = $this->postJson('/api/webhooks/clicksend/sms?secret=secret-key', [
            'from' => '+447777777777',
            'body' => 'Running late',
        ]);

        $response->assertStatus(200);

        // 3. Assert Team Details Updated
        $callout->refresh();
        $this->assertStringContainsString('Initial Team Info', $callout->team_details);
        $this->assertStringContainsString('[SMS from +447777777777]: Running late', $callout->team_details);
    }

    public function test_webhook_aborts_if_secret_is_invalid()
    {
        Config::set('services.clicksend.webhook_secret', 'secret-key');

        $response = $this->postJson('/api/webhooks/clicksend/sms?secret=wrong-key', [
            'from' => '+447777777777',
            'body' => 'OUT SAFE',
        ]);

        $response->assertStatus(401);
    }
}
