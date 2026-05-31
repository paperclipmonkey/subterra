<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDeletionStabilityTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function an_admin_can_delete_an_inactive_user()
    {
        $admin = User::factory()->admin()->create();

        // Users are created inactive by default when certain flags are set or via direct creation
        // The IsActiveScope filters where is_active is true.
        $inactiveUser = User::factory()->create([
            'is_active' => false,
            'email' => 'inactive@example.com',
        ]);

        // Sanity check: the inactive user should NOT be found via regular queries
        $this->assertNull(User::where('email', 'inactive@example.com')->first());

        $this->actingAs($admin, 'sanctum');

        // This would previously 500 because the middleware couldn't bind the model
        // and attempted to access ->id on a string.
        $response = $this->deleteJson("/api/users/{$inactiveUser->id}");

        $response->assertOk();
        $response->assertJson(['message' => 'Account deleted.']);

        // Verify deletion by checking the database directly without scopes
        $this->assertDatabaseMissing('users', ['id' => $inactiveUser->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function an_admin_can_delete_an_active_user()
    {
        $admin = User::factory()->admin()->create();
        $activeUser = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin, 'sanctum');
        $response = $this->deleteJson("/api/users/{$activeUser->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $activeUser->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_non_admin_cannot_delete_another_inactive_user()
    {
        $user = User::factory()->create();
        $inactiveUser = User::factory()->create(['is_active' => false]);

        $this->actingAs($user, 'sanctum');

        // Should be forbidden even if model binding fails to happen normally
        $response = $this->deleteJson("/api/users/{$inactiveUser->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $inactiveUser->id]);
    }
}
