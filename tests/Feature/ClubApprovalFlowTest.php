<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use App\Events\ClubAccessResponded;
use App\Listeners\ApproveClubUserAutomatically;

class ClubApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_becomes_approved_when_club_request_accepted()
    {
        $user = User::factory()->create(['is_approved' => false]);
        $club = Club::factory()->create();

        // Simulate club approval
        // We can manually trigger the listener or the event to test the integration
        
        $event = new ClubAccessResponded($club, $user, 'approved');
        $listener = new ApproveClubUserAutomatically();
        $listener->handle($event);

        $this->assertTrue($user->fresh()->is_approved);
    }

    public function test_user_does_not_become_approved_when_club_request_rejected()
    {
        $user = User::factory()->create(['is_approved' => false]);
        $club = Club::factory()->create();

        // Simulate club rejection
        $event = new ClubAccessResponded($club, $user, 'rejected');
        $listener = new ApproveClubUserAutomatically();
        $listener->handle($event);

        // THIS IS EXPECTED TO FAIL CURRENTLY due to the bug
        $this->assertFalse($user->fresh()->is_approved);
    }

    public function test_user_remains_approved_if_leaving_club()
    {
        $user = User::factory()->create(['is_approved' => true]);
        $club = Club::factory()->create();
        
        // Even if an event like "rejected" or "removed" happens (if we had one), 
        // the listener should not set it to false.
        // Current listener only sets to true, so this should pass.

        $event = new ClubAccessResponded($club, $user, 'rejected'); // or 'removed' if that existed
        $listener = new ApproveClubUserAutomatically();
        $listener->handle($event);

        $this->assertTrue($user->fresh()->is_approved);
    }
}
