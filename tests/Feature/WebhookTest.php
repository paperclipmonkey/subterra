<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.twilio.webhook_secret', $this->secret);
    }

    private function smsUrl(string $secret): string
    {
        return "/api/webhooks/twilio/{$secret}/sms";
    }

    // ---- secret enforcement ------------------------------------------------

    public function test_rejects_wrong_secret()
    {
        $this->postJson($this->smsUrl('wrong'), ['From' => '+447777777777', 'Body' => 'OUT SAFE'])
            ->assertStatus(403);
    }

    public function test_rejects_when_no_secret_configured()
    {
        Config::set('services.twilio.webhook_secret', '');
        $this->postJson($this->smsUrl('anything'), ['From' => '+447777777777', 'Body' => 'OUT SAFE'])
            ->assertStatus(403);
    }

    // ---- inbound SMS -------------------------------------------------------

    public function test_out_safe_cancels_callout()
    {
        $user = User::factory()->create(['phone' => '07777777777']);
        $callout = Callout::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'trip_plan' => 'Going caving',
        ]);

        $response = $this->post($this->smsUrl($this->secret), [
            'From' => '+447777777777',
            'Body' => '  out safe ',
        ]);

        $response->assertStatus(200);
        $response->assertSee('Glad you are safe', false);
        $this->assertEquals('cancelled', $callout->fresh()->status);
    }

    public function test_generic_message_is_logged_against_callout()
    {
        $user = User::factory()->create(['phone' => '+447777777777']);
        $callout = Callout::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'team_details' => 'Initial team info',
        ]);

        $response = $this->post($this->smsUrl($this->secret), [
            'From' => '07777777777',
            'Body' => 'Stuck at the second pitch',
        ]);

        $response->assertStatus(200)->assertSee('Message logged', false);
        $callout->refresh();
        $this->assertEquals('active', $callout->status);
        $this->assertStringContainsString('Stuck at the second pitch', $callout->team_details);
    }

    public function test_out_safe_leaves_open_incident_for_duty_officer_to_resolve()
    {
        // Regression (M5): a single inbound SMS must never close an open incident
        // mid-rescue. Like the in-app cancel path, the callout is cancelled but the
        // incident stays open for a duty officer to verify and resolve.
        $user = User::factory()->create(['phone' => '07777777777']);
        $callout = Callout::factory()->create([
            'user_id' => $user->id,
            'status' => 'triggered',
        ]);
        $incident = Incident::create(['callout_id' => $callout->id, 'status' => 'open']);

        $response = $this->post($this->smsUrl($this->secret), [
            'From' => '+447777777777',
            'Body' => 'OUT SAFE',
        ]);

        $response->assertStatus(200)->assertSee('Glad you are safe', false);

        $this->assertEquals('cancelled', $callout->fresh()->status);
        $this->assertEquals('open', $incident->fresh()->status, 'An inbound SMS must not resolve an open incident.');
        $this->assertNull($incident->fresh()->resolved_at);

        // The audit trail records both the safe-marking and its SMS source.
        $this->assertDatabaseHas('incident_notes', [
            'incident_id' => $incident->id,
            'user_id' => null,
            'content' => 'USER MARKED THEMSELVES SAFE via SMS. Please verify and resolve incident.',
        ]);
        $this->assertDatabaseHas('incident_notes', [
            'incident_id' => $incident->id,
            'user_id' => null,
            'content' => "Callout CANCELLED via SMS from +447777777777 saying 'OUT SAFE'.",
        ]);
    }

    public function test_out_safe_matches_participant_with_differently_formatted_number()
    {
        // Participant phones are normalised on write; the webhook's suffix matching must
        // find them regardless of the +44/0 prefix the caver texts from.
        $callout = Callout::factory()->create(['status' => 'active']);
        $callout->participants()->create([
            'name' => 'Ad-hoc Guest',
            'phone' => '07700900123', // stored normalised (no spaces)
        ]);

        $response = $this->post($this->smsUrl($this->secret), [
            'From' => '+447700900123',
            'Body' => 'OUT SAFE',
        ]);

        $response->assertStatus(200)->assertSee('Glad you are safe', false);
        $this->assertEquals('cancelled', $callout->fresh()->status);
    }

    public function test_no_active_callout_replies_gracefully()
    {
        $response = $this->post($this->smsUrl($this->secret), [
            'From' => '+447777777777',
            'Body' => 'OUT SAFE',
        ]);

        $response->assertStatus(200)->assertSee('No active callout found', false);
    }

    public function test_ack_from_duty_officer_acknowledges_open_incident()
    {
        $do = User::factory()->dutyOfficer()->create(['phone' => '07111111111', 'name' => 'Jane']);
        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create(['callout_id' => $callout->id, 'status' => 'open']);

        $response = $this->post($this->smsUrl($this->secret), [
            'From' => '+447111111111',
            'Body' => 'ACK',
        ]);

        $response->assertStatus(200)->assertSee('incident controller', false);
        $incident->refresh();
        $this->assertEquals($do->id, $incident->incident_controller_id);
        $this->assertEquals('managed', $incident->status);
    }

    // ---- voice -------------------------------------------------------------

    public function test_voice_twiml_contains_gather_and_acknowledge_prompt()
    {
        $do = User::factory()->dutyOfficer()->create(['phone' => '07111111111']);
        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create(['callout_id' => $callout->id, 'status' => 'open']);

        $response = $this->post("/api/webhooks/twilio/{$this->secret}/voice?incident={$incident->id}&user={$do->id}");

        $response->assertStatus(200)
            ->assertSee('<Gather', false)
            ->assertSee('Press 1', false);
    }

    public function test_voice_gather_press_1_acknowledges_incident()
    {
        $do = User::factory()->dutyOfficer()->create(['phone' => '07111111111', 'name' => 'Jane']);
        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create(['callout_id' => $callout->id, 'status' => 'open']);

        $response = $this->post(
            "/api/webhooks/twilio/{$this->secret}/voice/gather?incident={$incident->id}&user={$do->id}",
            ['Digits' => '1']
        );

        $response->assertStatus(200)->assertSee('acknowledged', false);
        $incident->refresh();
        $this->assertEquals($do->id, $incident->incident_controller_id);
        $this->assertEquals('managed', $incident->status);
    }

    public function test_voice_gather_with_unknown_user_does_not_acknowledge()
    {
        // Regression (M3): a press-1 from a call whose user id can't be resolved must
        // NOT acknowledge the incident — that would mark it managed with no controller
        // and silently stop escalation with nobody in charge.
        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create(['callout_id' => $callout->id, 'status' => 'open']);

        $response = $this->post(
            "/api/webhooks/twilio/{$this->secret}/voice/gather?incident={$incident->id}&user=999999",
            ['Digits' => '1']
        );

        $response->assertStatus(200)->assertSee('could not match', false);

        $incident->refresh();
        $this->assertNull($incident->incident_controller_id);
        $this->assertEquals('open', $incident->status, 'Escalation must continue when the acknowledger cannot be identified.');
    }

    public function test_voice_gather_no_digit_does_not_acknowledge()
    {
        $do = User::factory()->dutyOfficer()->create(['phone' => '07111111111']);
        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create(['callout_id' => $callout->id, 'status' => 'open']);

        $this->post("/api/webhooks/twilio/{$this->secret}/voice/gather?incident={$incident->id}&user={$do->id}", [])
            ->assertStatus(200);

        $this->assertNull($incident->fresh()->incident_controller_id);
    }

    public function test_voice_test_returns_confirmation_twiml()
    {
        $this->post("/api/webhooks/twilio/{$this->secret}/voice/test")
            ->assertStatus(200)
            ->assertSee('test call', false);
    }
}
