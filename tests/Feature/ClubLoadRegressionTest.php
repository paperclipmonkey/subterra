<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the launch-day outage fixes: the club member save, the heatmap and the
 * profile stats must stay cheap (bounded query counts) as data grows.
 */
class ClubLoadRegressionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function member_sync_query_count_does_not_grow_with_club_size(): void
    {
        $admin = User::factory()->admin()->create();
        $club = Club::factory()->create();
        $users = User::factory()->count(40)->create();
        foreach ($users as $user) {
            $club->users()->attach($user, ['status' => 'approved', 'is_admin' => false]);
        }

        $payload = ['members' => $users->map(fn ($u) => ['id' => $u->id, 'is_admin' => false])->all()];

        DB::enableQueryLog();
        $this->actingAs($admin)->putJson("/api/admin/clubs/{$club->slug}/members", $payload)->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // An unchanged 40-member roster must not issue per-member queries.
        $this->assertLessThan(25, $queries);
    }

    #[Test]
    public function member_sync_only_updates_changed_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $club = Club::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $club->users()->attach($a, ['status' => 'approved', 'is_admin' => false]);
        $club->users()->attach($b, ['status' => 'approved', 'is_admin' => false]);

        $this->actingAs($admin)->putJson("/api/admin/clubs/{$club->slug}/members", [
            'members' => [
                ['id' => $a->id, 'is_admin' => true],
                ['id' => $b->id, 'is_admin' => false],
            ],
        ])->assertOk();

        $this->assertTrue((bool) $club->users()->find($a->id)->pivot->is_admin);
        $this->assertFalse((bool) $club->users()->find($b->id)->pivot->is_admin);
    }

    #[Test]
    public function member_sync_rejects_unknown_user_ids(): void
    {
        $admin = User::factory()->admin()->create();
        $club = Club::factory()->create();

        $this->actingAs($admin)->putJson("/api/admin/clubs/{$club->slug}/members", [
            'members' => [['id' => 'does-not-exist', 'is_admin' => false]],
        ])->assertStatus(422);
    }

    #[Test]
    public function member_sync_rejects_non_scalar_ids_with_a_validation_error(): void
    {
        $admin = User::factory()->admin()->create();
        $club = Club::factory()->create();

        $this->actingAs($admin)->putJson("/api/admin/clubs/{$club->slug}/members", [
            'members' => [['id' => ['not', 'an', 'id'], 'is_admin' => false]],
        ])->assertStatus(422)->assertJson(['members.0.id' => ['The selected members.0.id is invalid.']]);
    }

    #[Test]
    public function member_sync_keeps_inactive_approved_members_the_roster_does_not_list(): void
    {
        $admin = User::factory()->admin()->create();
        $club = Club::factory()->create();
        $active = User::factory()->create();
        $inactive = User::factory()->create();
        $inactive->forceFill(['is_active' => false])->save();
        $club->users()->attach($active, ['status' => 'approved', 'is_admin' => false]);
        DB::table('club_user')->insert([
            'club_id' => $club->id, 'user_id' => $inactive->id, 'status' => 'approved', 'is_admin' => false,
        ]);

        // The roster the admin edits comes from approvedUsers(), which hides
        // inactive users, so saving it never lists them.
        $roster = $this->actingAs($admin)->getJson("/api/admin/clubs/{$club->slug}/members")->assertOk()->json();
        $this->assertSame([$active->id], array_column($roster, 'id'));

        $this->actingAs($admin)->putJson("/api/admin/clubs/{$club->slug}/members", [
            'members' => [['id' => $active->id, 'is_admin' => true]],
        ])->assertOk();

        $this->assertDatabaseHas('club_user', ['club_id' => $club->id, 'user_id' => $inactive->id, 'status' => 'approved']);
    }

    #[Test]
    public function platform_admin_can_add_an_inactive_user_as_before(): void
    {
        $admin = User::factory()->admin()->create();
        $club = Club::factory()->create();
        $inactive = User::factory()->create();
        $inactive->forceFill(['is_active' => false])->save();

        $this->actingAs($admin)->putJson("/api/admin/clubs/{$club->slug}/members", [
            'members' => [['id' => $inactive->id, 'is_admin' => false]],
        ])->assertOk();

        $this->assertDatabaseHas('club_user', ['club_id' => $club->id, 'user_id' => $inactive->id, 'status' => 'approved']);
    }

    #[Test]
    public function heatmap_multiplies_hours_by_approved_participants_only(): void
    {
        $club = Club::factory()->create();
        $approved1 = User::factory()->create();
        $approved2 = User::factory()->create();
        $outsider = User::factory()->create();
        $club->users()->attach($approved1, ['status' => 'approved']);
        $club->users()->attach($approved2, ['status' => 'approved']);

        $start = Carbon::now()->subDays(2);
        $trip = Trip::factory()->create([
            'visibility' => 'public',
            'start_time' => $start,
            'end_time' => $start->clone()->addHours(2),
        ]);
        $trip->participants()->attach([$approved1->id, $approved2->id, $outsider->id]);

        $response = $this->actingAs($approved1)->getJson("/api/clubs/{$club->slug}/activity-heatmap")->assertOk();

        $this->assertSame([['date' => $start->toDateString(), 'count' => 4]], $response->json());
    }

    #[Test]
    public function profile_stats_are_unchanged_with_slim_trip_loading(): void
    {
        $user = User::factory()->create();
        $start = Carbon::now()->subDays(3);
        $trips = Trip::factory()->count(2)->create([
            'visibility' => 'public',
            'start_time' => $start,
            'end_time' => $start->clone()->addMinutes(90),
        ]);
        foreach ($trips as $trip) {
            $trip->participants()->attach($user->id);
        }

        $expectedCaves = $trips->pluck('cave_system_id')->unique()->count();

        $me = $this->actingAs($user)->getJson('/api/users/me')->assertOk()->json('data.stats');
        $this->assertSame(['trips' => 2, 'caves' => $expectedCaves, 'duration' => 180], $me);

        $profile = $this->actingAs($user)->getJson("/api/users/{$user->id}")->assertOk()->json('data.stats');
        $this->assertSame(['trips' => 2, 'caves' => $expectedCaves, 'duration' => 180], $profile);
    }
}
