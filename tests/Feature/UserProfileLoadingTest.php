<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileLoadingTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_can_see_inactive_user_profile_but_sub_resources_fail_initially(): void
    {
        // 1. Create an admin user
        $admin = User::factory()->admin()->create(['is_active' => true]);

        // 2. Create an inactive user (filtered by Global Scope)
        $inactiveUser = User::factory()->create(['is_active' => false]);

        // 3. Authenticate as admin
        $this->actingAs($admin, 'sanctum');

        // 4. Admin tries to view profile - SHOULD SUCCEED (controller uses withoutGlobalScopes)
        $response = $this->getJson("/api/users/{$inactiveUser->id}");
        $response->assertOk();

        // 5. Admin tries to view sub-resources - SHOULD SUCCEED NOW

        // Recent Trips
        $responseTrips = $this->getJson("/api/users/{$inactiveUser->id}/recent-trips");
        $responseTrips->assertOk();

        // Activity Heatmap
        $responseHeatmap = $this->getJson("/api/users/{$inactiveUser->id}/activity-heatmap");
        $responseHeatmap->assertOk();
    }

    public static function inactiveProfileEndpoints(): array
    {
        return [
            'profile' => ['/api/users/%s'],
            'recent trips' => ['/api/users/%s/recent-trips'],
            'activity heatmap' => ['/api/users/%s/activity-heatmap'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('inactiveProfileEndpoints')]
    public function other_users_cannot_read_an_inactive_users_profile(string $path): void
    {
        // Placeholders and people who objected to being listed are inactive
        // precisely so they stop being findable.
        $inactiveUser = User::factory()->create(['is_active' => false]);

        foreach ([User::factory()->withApprovedClub()->create(), User::factory()->dataAdmin()->create()] as $viewer) {
            $this->actingAs($viewer, 'sanctum')
                ->getJson(sprintf($path, $inactiveUser->id))
                ->assertNotFound();
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('inactiveProfileEndpoints')]
    public function an_inactive_user_can_still_read_their_own_profile(string $path): void
    {
        $inactiveUser = User::factory()->create(['is_active' => false]);

        $this->actingAs($inactiveUser, 'sanctum')
            ->getJson(sprintf($path, $inactiveUser->id))
            ->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('inactiveProfileEndpoints')]
    public function active_profiles_remain_readable_by_other_users(string $path): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson(sprintf($path, $user->id))
            ->assertOk();
    }
}
