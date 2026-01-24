<?php

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnapprovedUserPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unapproved_user_cannot_see_sensitive_cave_data()
    {
        \App\Models\Tag::factory()->create(['tag' => 'Previously Done']);
        \App\Models\Tag::factory()->create(['tag' => 'Not Done Yet']);
        
        $user = User::factory()->create(['is_approved' => false]);
        $cave = Cave::factory()->create([
            'location_lat' => 54.2,
            'location_lng' => -2.5,
            'access_info' => 'Secret Key under the mat',
        ]);

        $response = $this->actingAs($user)->getJson("/api/caves/{$cave->slug}");

        $response->assertOk();
        
        // Assert sensitive data is hidden
        $response->assertJsonPath('data.location_lat', null);
        $response->assertJsonPath('data.location_lng', null);
        $response->assertJsonPath('data.access_info', null); // Or a placeholder message if decided
        
        // Assert public data is visible
        $response->assertJsonPath('data.name', $cave->name);

        // Assert system data restrictions
        $response->assertJsonPath('data.system.references', []);
        $response->assertJsonPath('data.system.files', []);
    }

    public function test_approved_user_can_see_sensitive_cave_data()
    {
        // Tags might already exist if database not refreshed per test? Using RefreshDatabase trait.
        // Traits run before each test.
        if (\App\Models\Tag::count() == 0) {
             \App\Models\Tag::factory()->create(['tag' => 'Previously Done']);
             \App\Models\Tag::factory()->create(['tag' => 'Not Done Yet']);
        }

        $user = User::factory()->create(['is_approved' => true]);
        $cave = Cave::factory()->create([
            'location_lat' => 54.2,
            'location_lng' => -2.5,
            'access_info' => 'Secret Key under the mat',
        ]);

        $response = $this->actingAs($user)->getJson("/api/caves/{$cave->slug}");

        $response->assertOk();
        
        $response->assertJsonPath('data.location_lat', 54.2);
        $response->assertJsonPath('data.location_lng', -2.5);
        $response->assertJsonPath('data.access_info', 'Secret Key under the mat');
    }

    public function test_unapproved_user_cannot_create_callout()
    {
        $user = User::factory()->create(['is_approved' => false]);
        
        // Even if admin is on call, this should fail
        $admin = User::factory()->create(['is_admin' => true]);
        \App\Models\OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHours(5),
        ]);

        $payload = [
            'callout_time' => now()->addHours(2)->toIso8601String(),
            'trip_plan' => 'Plan',
            'car_registration' => 'AB12',
            'car_parking' => 'Start',
            'participants' => [['name' => 'Me', 'phone' => '123']]
        ];

        $response = $this->actingAs($user)->postJson('/api/callouts', $payload);

        $response->assertStatus(403);
    }

    public function test_approved_user_can_create_callout()
    {
        // Mock SmsService? The existing CalloutTest mocks it. 
        // I might need to mock it here too to avoid actual SMS attempts if Service calls it.
        // However, if I only test the Controller *gate*, mocking isn't strictly necessary if it fails before service?
        // But for the "can create" test, it WILL hit the service.
        // So I'll mock standard services or rely on base test setup if any.
        // Actually Base TestCase doesn't mock SMS. 
        // I'll copy the mock setup from CalloutTest if needed, or rely on Mail::fake().
        
        $this->mock(\App\Services\SmsService::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->andReturn((object)['messageid' => '123']);
        });
        \Illuminate\Support\Facades\Mail::fake();

        $user = User::factory()->create(['is_approved' => true]);
        
        $admin = User::factory()->create(['is_admin' => true]);
        \App\Models\OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHours(5),
        ]);

        $payload = [
            'callout_time' => now()->addHours(2)->toIso8601String(),
            'trip_plan' => 'Plan',
            'car_registration' => 'AB12',
            'car_parking' => 'Start',
            'participants' => [['name' => 'Me', 'phone' => '123']]
        ];

        $response = $this->actingAs($user)->postJson('/api/callouts', $payload);

        $response->assertStatus(201);
    }
}
