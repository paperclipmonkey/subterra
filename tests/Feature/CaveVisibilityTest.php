<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CaveVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TagSeeder::class);
    }

    #[Test]
    public function admin_only_caves_are_excluded_from_the_public_list(): void
    {
        $this->actingAs(User::factory()->withApprovedClub()->create());
        Cave::factory()->create(['name' => 'Public Cave', 'visibility' => 'public']);
        Cave::factory()->create(['name' => 'Secret Mine', 'visibility' => 'admin_only']);

        $response = $this->getJson('/api/caves');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Public Cave'));
        $this->assertFalse($names->contains('Secret Mine'), 'admin_only cave must not appear in the public list');
    }

    #[Test]
    public function admin_only_caves_are_excluded_from_search(): void
    {
        $this->actingAs(User::factory()->withApprovedClub()->create());
        Cave::factory()->create(['name' => 'Findable Cave', 'visibility' => 'public']);
        Cave::factory()->create(['name' => 'Hidden Mine', 'visibility' => 'admin_only']);

        $response = $this->getJson('/api/caves/search?q=Cave');
        $response->assertStatus(200);
        $names = collect($response->json('data') ?? $response->json())->pluck('name')->filter();
        $this->assertFalse($names->contains('Hidden Mine'));
    }

    #[Test]
    public function an_authenticated_non_admin_gets_404_for_an_admin_only_cave(): void
    {
        $this->actingAs(User::factory()->create());
        $cave = Cave::factory()->create(['visibility' => 'admin_only']);

        $this->getJson('/api/caves/'.$cave->slug)->assertStatus(404);
    }

    #[Test]
    public function a_data_admin_can_view_any_admin_only_cave(): void
    {
        $admin = User::factory()->dataAdmin()->create();
        $cave = Cave::factory()->create(['visibility' => 'admin_only']);

        $this->actingAs($admin)
            ->getJson('/api/caves/'.$cave->slug)
            ->assertStatus(200)
            ->assertJsonPath('data.slug', $cave->slug);
    }
}
