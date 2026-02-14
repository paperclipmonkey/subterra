<?php

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
            'car_details' => 'Blue Ford Focus ABC 123',
            'team_details' => 'Medical condition: Asthma',
            'trip_plan' => 'Going to Swildons via short round',
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
            ->expectsOutput("Successfully scrubbed sensitive data from 1 callouts and their participants.")
            ->assertExitCode(0);

        // 4. Assertion: Verify old callout is scrubbed
        $callout->refresh();
        $this->assertNull($callout->car_details);
        $this->assertNull($callout->team_details);
        $this->assertNull($callout->trip_plan);

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
}
