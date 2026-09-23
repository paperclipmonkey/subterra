<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Deleting a user cascades to their callouts and incidents, so it must not be
 * possible while a callout is live or an incident is still being handled.
 */
class AccountDeletionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public static function liveStatuses(): array
    {
        return ['active' => ['active'], 'triggered' => ['triggered']];
    }

    #[Test]
    #[DataProvider('liveStatuses')]
    public function an_account_with_a_live_callout_cannot_be_deleted(string $status): void
    {
        $user = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $user->id, 'status' => $status]);

        $this->actingAs($user)->deleteJson('/api/users/me')->assertStatus(409);

        $this->assertNotNull(User::withoutGlobalScopes()->find($user->id));
        $this->assertNotNull($callout->fresh());
    }

    #[Test]
    public function an_account_whose_cancelled_callout_still_has_an_open_incident_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $user->id, 'status' => 'cancelled']);
        Incident::create(['callout_id' => $callout->id, 'status' => 'managed']);

        $this->actingAs($user)->deleteJson('/api/users/me')->assertStatus(409);
    }

    #[Test]
    public function an_admin_cannot_delete_someone_mid_trip_either(): void
    {
        $user = User::factory()->create();
        Callout::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/users/{$user->id}")
            ->assertStatus(409);
    }

    #[Test]
    public function an_account_with_only_finished_callouts_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $user->id, 'status' => 'cancelled']);
        Incident::create(['callout_id' => $callout->id, 'status' => 'resolved']);

        $this->actingAs($user)->deleteJson('/api/users/me')->assertOk();

        $this->assertNull(User::withoutGlobalScopes()->find($user->id));
    }
}
