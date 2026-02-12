<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function new_user_registration_records_consent_timestamp()
    {
        $payload = [
            'email' => 'newuser@example.com',
            'agreed_to_tos' => true,
        ];

        $response = $this->postJson('/api/auth/magic-link', $payload);

        $response->assertOk();

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user->tos_agreed_at);
        $this->assertNotNull($user->privacy_policy_agreed_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function current_user_returns_consent_timestamp()
    {
        $user = User::factory()->create([
            'privacy_policy_agreed_at' => now()->subDays(1),
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/users/me');

        $response->assertOk();
        $response->assertJsonPath('data.privacy_policy_agreed_at', $user->privacy_policy_agreed_at->toISOString());
    }
}
