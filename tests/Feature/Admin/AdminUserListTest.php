<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUserListTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_every_active_user_as_a_slim_row(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(4)->create();
        User::factory()->create(['is_active' => false]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'email', 'photo', 'clubs', 'roles', 'created_at'],
            array_keys($response->json('data.0')),
        );
    }
}
