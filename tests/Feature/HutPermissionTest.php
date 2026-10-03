<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Hut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HutPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_admin_can_update_hut()
    {
        $club = Club::factory()->create();
        $hut = Hut::factory()->create(['club_id' => $club->id]);
        $user = User::factory()->create();

        // Attach user as club admin
        $club->users()->attach($user->id, ['is_admin' => true, 'status' => 'approved']);

        $response = $this->actingAs($user)->putJson("/api/huts/{$hut->id}", [
            'name' => 'Updated Hut Name',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('huts', ['id' => $hut->id, 'name' => 'Updated Hut Name']);
    }

    public function test_regular_member_cannot_update_hut()
    {
        $club = Club::factory()->create();
        $hut = Hut::factory()->create(['club_id' => $club->id]);
        $user = User::factory()->create();

        // Attach user as regular member
        $club->users()->attach($user->id, ['is_admin' => false, 'status' => 'approved']);

        $response = $this->actingAs($user)->putJson("/api/huts/{$hut->id}", [
            'name' => 'Updated Hut Name',
        ]);

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_update_hut()
    {
        $club = Club::factory()->create();
        $hut = Hut::factory()->create(['club_id' => $club->id]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson("/api/huts/{$hut->id}", [
            'name' => 'Updated Hut Name',
        ]);

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_create_hut()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/huts', [
            'name' => 'Unauthorized Hut',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_create_hut()
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->postJson('/api/huts', [
            'name' => 'Admin Hut',
        ]);

        $response->assertStatus(201);
    }

    public function test_regular_user_cannot_delete_hut()
    {
        $user = User::factory()->create();
        $hut = Hut::factory()->create();

        $response = $this->actingAs($user)->deleteJson("/api/huts/{$hut->id}");

        $response->assertStatus(403);
    }

    public function test_club_admin_can_delete_own_club_hut()
    {
        $club = Club::factory()->create();
        $hut = Hut::factory()->create(['club_id' => $club->id]);
        $user = User::factory()->create();
        $club->users()->attach($user->id, ['is_admin' => true, 'status' => 'approved']);

        $response = $this->actingAs($user)->deleteJson("/api/huts/{$hut->id}");

        $response->assertStatus(204);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_club_admin_cannot_hand_their_hut_to_another_club(): void
    {
        $club = Club::factory()->create();
        $otherClub = Club::factory()->create();
        $hut = Hut::factory()->create(['club_id' => $club->id, 'name' => 'Original']);
        $user = User::factory()->create();
        $club->users()->attach($user->id, ['is_admin' => true, 'status' => 'approved']);

        $this->actingAs($user)->putJson("/api/huts/{$hut->id}", [
            'name' => 'Taken Over',
            'club_id' => $otherClub->id,
        ])->assertStatus(403);

        $this->actingAs($user)->putJson("/api/huts/{$hut->id}", [
            'name' => 'Orphaned',
            'club_id' => null,
        ])->assertStatus(403);

        $this->assertDatabaseHas('huts', ['id' => $hut->id, 'club_id' => $club->id, 'name' => 'Original']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_club_admin_can_resend_their_own_club_id(): void
    {
        // The edit form always sends club_id back unchanged.
        $club = Club::factory()->create();
        $hut = Hut::factory()->create(['club_id' => $club->id]);
        $user = User::factory()->create();
        $club->users()->attach($user->id, ['is_admin' => true, 'status' => 'approved']);

        $this->actingAs($user)->putJson("/api/huts/{$hut->id}", [
            'name' => 'Renamed',
            'club_id' => (string) $club->id,
        ])->assertOk();

        $this->assertDatabaseHas('huts', ['id' => $hut->id, 'club_id' => $club->id, 'name' => 'Renamed']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_platform_admin_can_move_a_hut_to_another_club(): void
    {
        $club = Club::factory()->create();
        $otherClub = Club::factory()->create();
        $hut = Hut::factory()->create(['club_id' => $club->id]);

        $this->actingAs(User::factory()->admin()->create())->putJson("/api/huts/{$hut->id}", [
            'name' => $hut->name,
            'club_id' => $otherClub->id,
        ])->assertOk();

        $this->assertDatabaseHas('huts', ['id' => $hut->id, 'club_id' => $otherClub->id]);
    }
}
