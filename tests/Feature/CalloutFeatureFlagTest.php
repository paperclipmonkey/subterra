<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\Cave;
use App\Models\OnCallShift;
use App\Models\User;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The `features.callouts` master switch: when callouts are off globally, nobody
 * can start one, whatever roles they hold — but anything already in flight must
 * still be resolvable.
 */
class CalloutFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(SmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendMessage')->andReturn((object) ['messageid' => 'mocked']);
        });
    }

    private function calloutPayload(Cave $cave): array
    {
        return [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'cave_id' => $cave->id,
            'description' => 'Test Trip',
            'trip_plan' => 'Detailed Plan',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Bull Pot Farm',
            'location_data' => ['latitude' => 54.2, 'longitude' => -2.5, 'accuracy' => 10],
            'team_details' => 'Alice, Bob',
            'participants' => [
                ['name' => 'Alice', 'email' => 'alice@test.com', 'phone' => '+111'],
            ],
        ];
    }

    private function withOnCallCover(): void
    {
        OnCallShift::create([
            'user_id' => User::factory()->dutyOfficer()->create()->id,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(5),
        ]);
    }

    #[Test]
    public function creating_a_callout_is_refused_when_the_feature_is_off(): void
    {
        config(['features.callouts' => false]);
        Mail::fake();

        $user = User::factory()->withApprovedClub()->create();
        $cave = Cave::factory()->create();
        $this->withOnCallCover();

        $this->actingAs($user)
            ->postJson('/api/callouts', $this->calloutPayload($cave))
            ->assertStatus(403);

        $this->assertDatabaseCount('callouts', 0);
    }

    #[Test]
    public function a_platform_admin_cannot_bypass_the_master_switch(): void
    {
        config(['features.callouts' => false]);
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $cave = Cave::factory()->create();
        $this->withOnCallCover();

        $this->actingAs($admin)
            ->postJson('/api/callouts', $this->calloutPayload($cave))
            ->assertStatus(403);
    }

    #[Test]
    public function an_active_callout_can_still_be_cancelled_when_the_feature_is_switched_off(): void
    {
        // Someone underground when the flag flips must be able to stand their
        // callout down — being unable to would trigger a false rescue.
        Mail::fake();
        $user = User::factory()->withApprovedClub()->create();
        $callout = Callout::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        config(['features.callouts' => false]);

        $this->actingAs($user)
            ->postJson("/api/callouts/{$callout->id}/cancel")
            ->assertStatus(200);

        $this->assertNotSame('active', $callout->fresh()->status);
    }

    #[Test]
    public function an_active_callout_can_still_be_viewed_when_the_feature_is_switched_off(): void
    {
        $callout = Callout::factory()->create(['status' => 'active']);

        config(['features.callouts' => false]);

        $this->getJson("/api/callouts/{$callout->id}")->assertStatus(200);
    }

    #[Test]
    public function no_role_grants_callout_access_while_the_feature_is_off(): void
    {
        config(['features.callouts' => false]);

        $this->assertFalse(User::factory()->admin()->create()->canUseCallout());
        $this->assertFalse(User::factory()->dutyOfficer()->create()->canUseCallout());

        config(['features.callouts' => true]);

        $this->assertTrue(User::factory()->admin()->create()->canUseCallout());
    }

    #[Test]
    public function the_current_user_payload_advertises_whether_callouts_are_available(): void
    {
        $user = User::factory()->create();

        config(['features.callouts' => false]);
        $this->actingAs($user)->getJson('/api/users/me')
            ->assertStatus(200)
            ->assertJsonPath('data.features.callouts', false);

        config(['features.callouts' => true]);
        $this->actingAs($user)->getJson('/api/users/me')
            ->assertStatus(200)
            ->assertJsonPath('data.features.callouts', true);
    }

    #[Test]
    public function creating_a_callout_still_works_with_the_feature_on(): void
    {
        config(['features.callouts' => true]);
        Mail::fake();

        $user = User::factory()->withApprovedClub()->create();
        $cave = Cave::factory()->create();
        $this->withOnCallCover();

        $this->actingAs($user)
            ->postJson('/api/callouts', $this->calloutPayload($cave))
            ->assertStatus(201);
    }
}
