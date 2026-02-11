<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileLoadingTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_can_see_inactive_user_profile_but_sub_resources_fail_initially(): void
    {
        // 1. Create an admin user
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

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
}
