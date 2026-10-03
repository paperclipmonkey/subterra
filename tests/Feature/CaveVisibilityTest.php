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

    #[Test]
    public function the_cave_page_hides_the_system_annotation_without_an_approved_club(): void
    {
        // Parking spots and approach paths pinpoint the entrances: same gate as
        // CaveSystemResource and the annotations endpoint.
        $cave = Cave::factory()->create();
        \App\Models\CaveSystemAnnotation::factory()->create(['cave_system_id' => $cave->cave_system_id]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/caves/{$cave->id}")
            ->assertOk()
            ->assertJsonPath('data.system.annotation', null);
    }

    #[Test]
    public function the_cave_page_shows_the_system_annotation_to_approved_club_members_and_data_admins(): void
    {
        $cave = Cave::factory()->create();
        \App\Models\CaveSystemAnnotation::factory()->create(['cave_system_id' => $cave->cave_system_id]);

        foreach ([User::factory()->withApprovedClub()->create(), User::factory()->dataAdmin()->create()] as $user) {
            $this->actingAs($user)
                ->getJson("/api/caves/{$cave->id}")
                ->assertOk()
                ->assertJsonPath('data.system.annotation.cave_system_id', $cave->cave_system_id)
                ->assertJsonPath('data.system.annotation.geojson.type', 'FeatureCollection');
        }
    }
}
