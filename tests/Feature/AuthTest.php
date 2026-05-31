<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_get_does_not_log_out()
    {
        $user = User::factory()->create();

        // GET to /api/logout should not match the POST route or perform a logout
        $response = $this->actingAs($user)->getJson('/api/logout');

        // The route is POST-only, so GET should not perform a logout
        // Depending on routing, it might 405 or 404 (if not defined for GET)
        $this->assertNotEquals(
            'Logged out',
            $response->json('message') ?? null,
        );
    }

    public function test_logout_via_post_works()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/logout');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Logged out']);
    }
}
