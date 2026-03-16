<?php

namespace Tests\Feature\Admin;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\CaveSystemFile;
use App\Models\Route as CaveRoute;
use App\Models\SuggestedEdit;
use App\Models\Tag;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaveSystemDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $dataAdmin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->dataAdmin = User::factory()->dataAdmin()->create();
        $this->regularUser = User::factory()->create();
    }

    public function test_data_admin_can_delete_empty_cave_system()
    {
        $system = CaveSystem::factory()->create(['name' => 'Empty System']);

        $response = $this->actingAs($this->dataAdmin)
            ->deleteJson("/api/admin/cave-systems/{$system->id}");

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Cave system "Empty System" has been deleted.']);
        $this->assertDatabaseMissing('cave_systems', ['id' => $system->id]);
    }

    public function test_platform_admin_can_delete_cave_system()
    {
        $system = CaveSystem::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/admin/cave-systems/{$system->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('cave_systems', ['id' => $system->id]);
    }

    public function test_regular_user_cannot_delete_cave_system()
    {
        $system = CaveSystem::factory()->create();

        $response = $this->actingAs($this->regularUser)
            ->deleteJson("/api/admin/cave-systems/{$system->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('cave_systems', ['id' => $system->id]);
    }

    public function test_unauthenticated_user_cannot_delete_cave_system()
    {
        $system = CaveSystem::factory()->create();

        $response = $this->deleteJson("/api/admin/cave-systems/{$system->id}");

        $response->assertUnauthorized();
        $this->assertDatabaseHas('cave_systems', ['id' => $system->id]);
    }

    public function test_cannot_delete_cave_system_with_trips()
    {
        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);
        Trip::factory()->create([
            'cave_system_id' => $system->id,
            'entrance_cave_id' => $cave->id,
            'exit_cave_id' => $cave->id,
            'start_time' => now(),
        ]);

        $response = $this->actingAs($this->dataAdmin)
            ->deleteJson("/api/admin/cave-systems/{$system->id}");

        $response->assertUnprocessable();
        $response->assertJsonFragment(['error' => 'Cannot delete a cave system that has trips. Remove all trips first.']);
        $this->assertDatabaseHas('cave_systems', ['id' => $system->id]);
    }

    public function test_delete_removes_associated_caves()
    {
        $system = CaveSystem::factory()->create();
        $cave1 = Cave::factory()->create(['cave_system_id' => $system->id]);
        $cave2 = Cave::factory()->create(['cave_system_id' => $system->id]);

        $response = $this->actingAs($this->dataAdmin)
            ->deleteJson("/api/admin/cave-systems/{$system->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('caves', ['id' => $cave1->id]);
        $this->assertDatabaseMissing('caves', ['id' => $cave2->id]);
    }

    public function test_delete_removes_associated_routes()
    {
        $system = CaveSystem::factory()->create();
        $route = CaveRoute::factory()->create(['cave_system_id' => $system->id]);

        $response = $this->actingAs($this->dataAdmin)
            ->deleteJson("/api/admin/cave-systems/{$system->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('routes', ['id' => $route->id]);
    }

    public function test_delete_removes_associated_tags()
    {
        $system = CaveSystem::factory()->create();
        $tag = Tag::factory()->create();
        $system->tags()->attach($tag);

        $response = $this->actingAs($this->dataAdmin)
            ->deleteJson("/api/admin/cave-systems/{$system->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('cave_system_tag', [
            'cave_system_id' => $system->id,
        ]);
    }

    public function test_delete_removes_associated_files()
    {
        Storage::fake('media');

        $system = CaveSystem::factory()->create();
        $file = CaveSystemFile::create([
            'cave_system_id' => $system->id,
            'filename' => 'test.pdf',
            'original_filename' => 'test.pdf',
            'thumbnail_filename' => 'test_thumb.jpg',
        ]);

        Storage::disk('media')->put("cave_system_files/{$system->id}/test.pdf", 'content');
        Storage::disk('media')->put("cave_system_files/{$system->id}/test_thumb.jpg", 'thumb');

        $response = $this->actingAs($this->dataAdmin)
            ->deleteJson("/api/admin/cave-systems/{$system->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('cave_system_files', ['id' => $file->id]);
        Storage::disk('media')->assertMissing("cave_system_files/{$system->id}/test.pdf");
        Storage::disk('media')->assertMissing("cave_system_files/{$system->id}/test_thumb.jpg");
    }

    public function test_delete_removes_suggested_edits_for_system_and_caves()
    {
        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);

        $systemEdit = SuggestedEdit::create([
            'user_id' => null,
            'suggestable_type' => CaveSystem::class,
            'suggestable_id' => $system->id,
            'original_data' => ['name' => 'Old'],
            'suggested_data' => ['name' => 'New'],
            'status' => 'pending',
        ]);

        $caveEdit = SuggestedEdit::create([
            'user_id' => null,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => ['name' => 'Old Cave'],
            'suggested_data' => ['name' => 'New Cave'],
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->dataAdmin)
            ->deleteJson("/api/admin/cave-systems/{$system->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('suggested_edits', ['id' => $systemEdit->id]);
        $this->assertDatabaseMissing('suggested_edits', ['id' => $caveEdit->id]);
    }

    public function test_delete_nonexistent_system_returns_404()
    {
        $response = $this->actingAs($this->dataAdmin)
            ->deleteJson('/api/admin/cave-systems/99999');

        $response->assertNotFound();
    }
}
