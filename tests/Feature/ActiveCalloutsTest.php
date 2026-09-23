<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActiveCalloutsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function active_callouts_list_does_not_expose_the_cancel_capability_id(): void
    {
        $owner = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $owner->id, 'status' => 'active']);

        $response = $this->actingAs(User::factory()->withApprovedClub()->create())
            ->getJson('/api/callouts/active')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertArrayNotHasKey('id', $response->json('data.0'));
        $this->assertStringNotContainsString($callout->id, $response->getContent());
    }

    #[Test]
    public function users_without_an_approved_club_cannot_see_open_trips(): void
    {
        Callout::factory()->create(['user_id' => User::factory()->create()->id, 'status' => 'active']);

        // callout_access (granted to new sign-ups by default) is not enough.
        $user = User::factory()->create();
        $user->assignRole('callout_access');

        $this->actingAs($user)->getJson('/api/callouts/active')->assertForbidden();
    }

    #[Test]
    public function duty_officers_can_see_open_trips_without_a_club(): void
    {
        Callout::factory()->create(['user_id' => User::factory()->create()->id, 'status' => 'active']);

        $this->actingAs(User::factory()->dutyOfficer()->create())
            ->getJson('/api/callouts/active')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
