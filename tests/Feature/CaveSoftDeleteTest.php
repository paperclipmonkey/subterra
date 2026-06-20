<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CaveSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function deleting_a_cave_soft_deletes_it_and_hides_it_from_normal_queries(): void
    {
        $admin = User::factory()->dataAdmin()->create();
        $cave = Cave::factory()->create();

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->deleteJson('/api/caves/'.$cave->slug)
            ->assertStatus(204);

        // Row still exists but is soft-deleted and excluded from default scope.
        $this->assertSoftDeleted('caves', ['id' => $cave->id]);
        $this->assertNull(Cave::find($cave->id));
        $this->assertNotNull(Cave::withTrashed()->find($cave->id));
    }

    #[Test]
    public function a_data_admin_can_restore_a_soft_deleted_cave(): void
    {
        $admin = User::factory()->dataAdmin()->create();
        $cave = Cave::factory()->create();
        $cave->delete();

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/caves/'.$cave->slug.'/restore')
            ->assertStatus(200);

        $this->assertNotSoftDeleted('caves', ['id' => $cave->id]);
    }

    #[Test]
    public function a_non_admin_cannot_restore_a_cave(): void
    {
        $cave = Cave::factory()->create();
        $cave->delete();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/caves/'.$cave->slug.'/restore')
            ->assertStatus(403);
    }
}
