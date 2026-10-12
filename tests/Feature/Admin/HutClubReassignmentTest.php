<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Club;
use App\Models\Hut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HutClubReassignmentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function club_admin_cannot_move_their_hut_to_another_club(): void
    {
        $club = Club::factory()->create();
        $otherClub = Club::factory()->create();
        $hut = Hut::factory()->create(['club_id' => $club->id]);
        $clubAdmin = User::factory()->create();
        $club->users()->attach($clubAdmin->id, ['is_admin' => true, 'status' => 'approved']);

        $this->actingAs($clubAdmin)->putJson("/api/huts/{$hut->id}", [
            'name' => 'Renamed',
            'club_id' => $otherClub->id,
        ])->assertForbidden();

        $this->assertDatabaseHas('huts', ['id' => $hut->id, 'club_id' => $club->id, 'name' => $hut->name]);
    }
}
