<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\CalloutParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeOldCalloutsTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_scrubs_pii_from_old_callouts_and_participants()
    {
        // 1. Setup: Create a callout older than 30 days with PII
        $oldDate = Carbon::now()->subDays(31);

        $user = User::factory()->create();

        $callout = Callout::factory()->create([
            'created_at' => $oldDate,
            'status' => 'cancelled',
            'description' => 'Going to Swildons via short round',
            'car_details' => 'Blue Ford Focus ABC 123',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Bull Pot Farm',
            'team_details' => 'Medical condition: Asthma',
            'trip_plan' => 'Going to Swildons via short round',
            'location_data' => ['latitude' => 54.2, 'longitude' => -2.5],
            'request_data' => ['ip' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0'],
            'cancelled_ip' => '203.0.113.9',
            'cancelled_user_agent' => 'Mozilla/5.0 (cancel)',
            'user_id' => $user->id,
        ]);

        $participant = CalloutParticipant::create([
            'callout_id' => $callout->id,
            'name' => 'John Doe',
            'phone' => '07123456789',
            'email' => 'john@example.com',
            'user_id' => User::factory()->create()->id,
        ]);

        // 2. Setup: Create a recent callout (should NOT be scrubbed)
        $recentCallout = Callout::factory()->create([
            'created_at' => Carbon::now()->subDays(5),
            'status' => 'active',
            'car_details' => 'Stay here',
        ]);

        // 3. Execution: Run the command
        $this->artisan('callouts:purge-sensitive-data')
            ->expectsOutput('Successfully scrubbed sensitive data from 1 callouts and their participants.')
            ->assertExitCode(0);

        // 4. Assertion: Verify old callout is scrubbed
        $callout->refresh();
        $this->assertEquals('Scrubbed', $callout->description);
        $this->assertNull($callout->car_details);
        $this->assertNull($callout->car_registration);
        $this->assertNull($callout->car_parking);
        $this->assertNull($callout->team_details);
        $this->assertNull($callout->trip_plan);
        $this->assertNull($callout->location_data);
        $this->assertNull($callout->request_data);
        $this->assertNull($callout->cancelled_ip);
        $this->assertNull($callout->cancelled_user_agent);

        // Verify old participant is scrubbed
        $participant->refresh();
        $this->assertEquals('Scrubbed Participant', $participant->name);
        $this->assertNull($participant->phone);
        $this->assertNull($participant->email);
        $this->assertNotNull($participant->user_id); // Ensure association remains

        // Verify recent callout is NOT scrubbed
        $recentCallout->refresh();
        $this->assertEquals('Stay here', $recentCallout->car_details);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_converges_and_does_not_rescrub_already_scrubbed_callouts()
    {
        // Regression (L9): the selection predicate used to match already-scrubbed
        // callouts forever (participants.name is rewritten, never nulled). A second run
        // must find nothing left to scrub.
        $callout = Callout::factory()->create([
            'created_at' => Carbon::now()->subDays(31),
            'status' => 'resolved',
            'description' => 'Original plan',
            'trip_plan' => 'Original plan',
        ]);

        CalloutParticipant::create([
            'callout_id' => $callout->id,
            'name' => 'John Doe',
            'phone' => '07123456789',
            'email' => 'john@example.com',
        ]);

        $this->artisan('callouts:purge-sensitive-data')
            ->expectsOutput('Successfully scrubbed sensitive data from 1 callouts and their participants.')
            ->assertExitCode(0);

        $this->artisan('callouts:purge-sensitive-data')
            ->expectsOutput('No old callouts require scrubbing.')
            ->assertExitCode(0);
    }
}
