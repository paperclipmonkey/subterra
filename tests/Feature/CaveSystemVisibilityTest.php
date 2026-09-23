<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\CaveSystemFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The cave-system endpoints must apply the same gates as CaveResource: locations,
 * access info, references and files only for approved-club members (private files
 * and admin_only sites only for data admins).
 */
class CaveSystemVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private CaveSystem $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->system = CaveSystem::factory()->create(['references' => 'Secret survey ref']);
        Cave::factory()->create([
            'cave_system_id' => $this->system->id,
            'name' => 'Public Entrance',
            'location_lat' => 54.1,
            'location_lng' => -2.4,
            'access_info' => 'Ask the farmer',
        ]);
        Cave::factory()->create([
            'cave_system_id' => $this->system->id,
            'name' => 'Old Coal Mine',
            'visibility' => 'admin_only',
        ]);
        CaveSystemFile::factory()->for($this->system, 'caveSystem')->create(['title' => 'Public Survey']);
        CaveSystemFile::factory()->for($this->system, 'caveSystem')->private()->create(['title' => 'Private Map']);
    }

    private function fetch(User $user, bool $index): array
    {
        if ($index) {
            $data = $this->actingAs($user)->getJson('/api/cave_systems')->assertOk()->json('data');

            return collect($data)->firstWhere('id', $this->system->id);
        }

        return $this->actingAs($user)->getJson("/api/cave_systems/{$this->system->id}")->assertOk()->json('data');
    }

    public static function endpoints(): array
    {
        return ['index' => [true], 'show' => [false]];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('endpoints')]
    public function users_without_an_approved_club_get_no_locations_files_or_hidden_sites(bool $index): void
    {
        $data = $this->fetch(User::factory()->create(), $index);

        $this->assertSame(['Public Entrance'], array_column($data['caves'], 'name'));
        $this->assertNull($data['caves'][0]['location_lat']);
        $this->assertNull($data['caves'][0]['location_lng']);
        $this->assertNull($data['caves'][0]['access_info']);
        $this->assertNull($data['references']);
        $this->assertSame([], $data['files']);
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('endpoints')]
    public function approved_club_members_get_locations_and_public_files_only(bool $index): void
    {
        $data = $this->fetch(User::factory()->withApprovedClub()->create(), $index);

        $this->assertSame(['Public Entrance'], array_column($data['caves'], 'name'));
        $this->assertEquals(54.1, $data['caves'][0]['location_lat']);
        $this->assertSame('Ask the farmer', $data['caves'][0]['access_info']);
        $this->assertSame('Secret survey ref', $data['references']);
        $this->assertSame(['Public Survey'], array_column($data['files'], 'title'));
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('endpoints')]
    public function data_admins_see_everything(bool $index): void
    {
        $data = $this->fetch(User::factory()->dataAdmin()->create(), $index);

        $this->assertEqualsCanonicalizing(['Public Entrance', 'Old Coal Mine'], array_column($data['caves'], 'name'));
        $this->assertEqualsCanonicalizing(['Public Survey', 'Private Map'], array_column($data['files'], 'title'));
    }

    #[Test]
    public function a_cave_page_does_not_list_admin_only_siblings_to_ordinary_users(): void
    {
        $public = $this->system->caves()->where('name', 'Public Entrance')->first();

        $siblings = $this->actingAs(User::factory()->withApprovedClub()->create())
            ->getJson("/api/caves/{$public->slug}")
            ->assertOk()
            ->json('data.system.caves');
        $this->assertSame(['Public Entrance'], array_column($siblings, 'name'));

        $siblings = $this->actingAs(User::factory()->dataAdmin()->create())
            ->getJson("/api/caves/{$public->slug}")
            ->json('data.system.caves');
        $this->assertEqualsCanonicalizing(['Public Entrance', 'Old Coal Mine'], array_column($siblings, 'name'));
    }
}
