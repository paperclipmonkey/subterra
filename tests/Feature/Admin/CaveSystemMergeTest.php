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

class CaveSystemMergeTest extends TestCase
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

    public function test_data_admin_can_merge_cave_systems()
    {
        $target = CaveSystem::factory()->create(['name' => 'Target System']);
        $source = CaveSystem::factory()->create(['name' => 'Source System']);

        $cave = Cave::factory()->create(['cave_system_id' => $source->id]);

        $response = $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Cave system "Source System" has been merged into "Target System".']);

        // Cave should now belong to target
        $this->assertEquals($target->id, $cave->fresh()->cave_system_id);

        // Source should be deleted
        $this->assertDatabaseMissing('cave_systems', ['id' => $source->id]);
    }

    public function test_platform_admin_can_merge_cave_systems()
    {
        $target = CaveSystem::factory()->create();
        $source = CaveSystem::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ]);

        $response->assertOk();
        $this->assertDatabaseMissing('cave_systems', ['id' => $source->id]);
    }

    public function test_regular_user_cannot_merge_cave_systems()
    {
        $target = CaveSystem::factory()->create();
        $source = CaveSystem::factory()->create();

        $response = $this->actingAs($this->regularUser)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_merge()
    {
        $target = CaveSystem::factory()->create();
        $source = CaveSystem::factory()->create();

        $response = $this->postJson("/api/admin/cave-systems/{$target->id}/merge", [
            'source_id' => $source->id,
        ]);

        $response->assertUnauthorized();
    }

    public function test_cannot_merge_system_into_itself()
    {
        $system = CaveSystem::factory()->create();

        $response = $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$system->id}/merge", [
                'source_id' => $system->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonFragment(['error' => 'Cannot merge a cave system into itself.']);
    }

    public function test_cannot_merge_nonexistent_source()
    {
        $target = CaveSystem::factory()->create();

        $response = $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => 99999,
            ]);

        $response->assertUnprocessable();
    }

    public function test_merge_migrates_caves()
    {
        $target = CaveSystem::factory()->create();
        $source = CaveSystem::factory()->create();

        $cave1 = Cave::factory()->create(['cave_system_id' => $source->id]);
        $cave2 = Cave::factory()->create(['cave_system_id' => $source->id]);
        $targetCave = Cave::factory()->create(['cave_system_id' => $target->id]);

        $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ])
            ->assertOk();

        $this->assertEquals($target->id, $cave1->fresh()->cave_system_id);
        $this->assertEquals($target->id, $cave2->fresh()->cave_system_id);
        $this->assertEquals($target->id, $targetCave->fresh()->cave_system_id);
        $this->assertEquals(3, Cave::where('cave_system_id', $target->id)->count());
    }

    public function test_merge_migrates_routes()
    {
        $target = CaveSystem::factory()->create();
        $source = CaveSystem::factory()->create();

        $route = CaveRoute::factory()->create(['cave_system_id' => $source->id]);

        $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ])
            ->assertOk();

        $this->assertEquals($target->id, $route->fresh()->cave_system_id);
    }

    public function test_merge_migrates_trips()
    {
        $target = CaveSystem::factory()->create();
        $source = CaveSystem::factory()->create();

        $trip = Trip::factory()->create(['cave_system_id' => $source->id]);

        $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ])
            ->assertOk();

        $this->assertEquals($target->id, $trip->fresh()->cave_system_id);
    }

    public function test_merge_migrates_files()
    {
        Storage::fake('media');

        $target = CaveSystem::factory()->create();
        $source = CaveSystem::factory()->create();

        // Create a file on disk and in DB for the source system
        Storage::disk('media')->put("cave_system_files/{$source->id}/test.pdf", 'content');

        $file = CaveSystemFile::create([
            'cave_system_id' => $source->id,
            'filename' => 'test.pdf',
            'original_filename' => 'Test Document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ])
            ->assertOk();

        // File record should point to target
        $this->assertEquals($target->id, $file->fresh()->cave_system_id);

        // File should be moved on disk
        Storage::disk('media')->assertExists("cave_system_files/{$target->id}/test.pdf");
        Storage::disk('media')->assertMissing("cave_system_files/{$source->id}/test.pdf");
    }

    public function test_merge_migrates_tags_without_duplicates()
    {
        $target = CaveSystem::factory()->create();
        $source = CaveSystem::factory()->create();

        $sharedTag = Tag::factory()->create(['tag' => 'Shared']);
        $sourceTag = Tag::factory()->create(['tag' => 'Source Only']);
        $targetTag = Tag::factory()->create(['tag' => 'Target Only']);

        $target->tags()->attach([$sharedTag->id, $targetTag->id]);
        $source->tags()->attach([$sharedTag->id, $sourceTag->id]);

        $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ])
            ->assertOk();

        $targetTags = $target->fresh()->tags()->pluck('tags.id')->sort()->values();
        $this->assertCount(3, $targetTags);
        $this->assertTrue($targetTags->contains($sharedTag->id));
        $this->assertTrue($targetTags->contains($sourceTag->id));
        $this->assertTrue($targetTags->contains($targetTag->id));
    }

    public function test_merge_migrates_suggested_edits()
    {
        $target = CaveSystem::factory()->create();
        $source = CaveSystem::factory()->create();

        $edit = SuggestedEdit::create([
            'user_id' => null,
            'suggestable_type' => CaveSystem::class,
            'suggestable_id' => $source->id,
            'original_data' => ['description' => 'old'],
            'suggested_data' => ['description' => 'new'],
            'status' => 'pending',
        ]);

        $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ])
            ->assertOk();

        $this->assertEquals($target->id, $edit->fresh()->suggestable_id);
        $this->assertEquals(CaveSystem::class, $edit->fresh()->suggestable_type);
    }

    public function test_merge_takes_larger_length_and_vertical_range()
    {
        $target = CaveSystem::factory()->create([
            'length' => 100,
            'vertical_range' => 50,
        ]);
        $source = CaveSystem::factory()->create([
            'length' => 500,
            'vertical_range' => 20,
        ]);

        $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ])
            ->assertOk();

        $target->refresh();
        $this->assertEquals(500, $target->length);
        $this->assertEquals(50, $target->vertical_range);
    }

    public function test_merge_fills_blank_metadata_from_source()
    {
        $target = CaveSystem::factory()->create([
            'description' => null,
            'references' => null,
        ]);
        $source = CaveSystem::factory()->create([
            'description' => 'Source description',
            'references' => '- Source reference',
        ]);

        $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ])
            ->assertOk();

        $target->refresh();
        $this->assertEquals('Source description', $target->description);
        $this->assertEquals('- Source reference', $target->references);
    }

    public function test_merge_preserves_target_metadata_when_not_empty()
    {
        $target = CaveSystem::factory()->create([
            'description' => 'Target description',
            'references' => '- Target reference',
        ]);
        $source = CaveSystem::factory()->create([
            'description' => 'Source description',
            'references' => '- Source reference',
        ]);

        $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ])
            ->assertOk();

        $target->refresh();
        $this->assertEquals('Target description', $target->description);
        $this->assertEquals('- Target reference', $target->references);
    }

    public function test_merge_deletes_source_system()
    {
        $target = CaveSystem::factory()->create();
        $source = CaveSystem::factory()->create();

        $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ])
            ->assertOk();

        $this->assertDatabaseMissing('cave_systems', ['id' => $source->id]);
        $this->assertDatabaseHas('cave_systems', ['id' => $target->id]);
    }

    public function test_merge_preview_returns_correct_counts()
    {
        $target = CaveSystem::factory()->create(['name' => 'Target System']);
        $source = CaveSystem::factory()->create(['name' => 'Source System']);

        Cave::factory()->count(2)->create(['cave_system_id' => $target->id]);
        Cave::factory()->count(3)->create(['cave_system_id' => $source->id]);
        CaveRoute::factory()->create(['cave_system_id' => $source->id]);

        $response = $this->actingAs($this->dataAdmin)
            ->getJson("/api/admin/cave-systems/{$target->id}/merge-preview?source_id={$source->id}");

        $response->assertOk();
        $response->assertJsonFragment([
            'target' => [
                'id' => $target->id,
                'name' => 'Target System',
                'caves_count' => 2,
                'routes_count' => 0,
                'files_count' => 0,
            ],
        ]);
        $response->assertJsonFragment([
            'source' => [
                'id' => $source->id,
                'name' => 'Source System',
                'caves_count' => 3,
                'routes_count' => 1,
                'files_count' => 0,
            ],
        ]);
        $response->assertJsonPath('result.caves_count', 5);
        $response->assertJsonPath('result.routes_count', 1);
        $response->assertJsonPath('result.source_will_be_deleted', true);
    }

    public function test_merge_preview_requires_admin()
    {
        $target = CaveSystem::factory()->create();
        $source = CaveSystem::factory()->create();

        $response = $this->actingAs($this->regularUser)
            ->getJson("/api/admin/cave-systems/{$target->id}/merge-preview?source_id={$source->id}");

        $response->assertForbidden();
    }

    public function test_merge_preview_cannot_preview_self()
    {
        $system = CaveSystem::factory()->create();

        $response = $this->actingAs($this->dataAdmin)
            ->getJson("/api/admin/cave-systems/{$system->id}/merge-preview?source_id={$system->id}");

        $response->assertUnprocessable();
    }

    public function test_source_id_is_required_for_merge()
    {
        $target = CaveSystem::factory()->create();

        $response = $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", []);

        $response->assertUnprocessable();
    }

    public function test_merge_handles_file_with_thumbnail()
    {
        Storage::fake('media');

        $target = CaveSystem::factory()->create();
        $source = CaveSystem::factory()->create();

        Storage::disk('media')->put("cave_system_files/{$source->id}/image.jpg", 'image-content');
        Storage::disk('media')->put("cave_system_files/{$source->id}/thumb_image.jpg", 'thumb-content');

        $file = CaveSystemFile::create([
            'cave_system_id' => $source->id,
            'filename' => 'image.jpg',
            'thumbnail_filename' => 'thumb_image.jpg',
            'original_filename' => 'Photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 200,
        ]);

        $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ])
            ->assertOk();

        // Both file and thumbnail should be moved
        Storage::disk('media')->assertExists("cave_system_files/{$target->id}/image.jpg");
        Storage::disk('media')->assertExists("cave_system_files/{$target->id}/thumb_image.jpg");
        Storage::disk('media')->assertMissing("cave_system_files/{$source->id}/image.jpg");
        Storage::disk('media')->assertMissing("cave_system_files/{$source->id}/thumb_image.jpg");
    }

    public function test_merge_with_all_relation_types_together()
    {
        Storage::fake('media');

        $target = CaveSystem::factory()->create(['name' => 'Master System', 'length' => 100]);
        $source = CaveSystem::factory()->create(['name' => 'Old System', 'length' => 200]);

        // Create relations on source
        $cave = Cave::factory()->create(['cave_system_id' => $source->id]);
        $route = CaveRoute::factory()->create(['cave_system_id' => $source->id]);
        $trip = Trip::factory()->create(['cave_system_id' => $source->id]);
        $tag = Tag::factory()->create();
        $source->tags()->attach($tag);

        Storage::disk('media')->put("cave_system_files/{$source->id}/doc.pdf", 'content');
        $file = CaveSystemFile::create([
            'cave_system_id' => $source->id,
            'filename' => 'doc.pdf',
            'original_filename' => 'Document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 50,
        ]);

        $edit = SuggestedEdit::create([
            'user_id' => null,
            'suggestable_type' => CaveSystem::class,
            'suggestable_id' => $source->id,
            'original_data' => ['description' => 'old'],
            'suggested_data' => ['description' => 'new'],
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->dataAdmin)
            ->postJson("/api/admin/cave-systems/{$target->id}/merge", [
                'source_id' => $source->id,
            ]);

        $response->assertOk();

        // Everything migrated
        $this->assertEquals($target->id, $cave->fresh()->cave_system_id);
        $this->assertEquals($target->id, $route->fresh()->cave_system_id);
        $this->assertEquals($target->id, $trip->fresh()->cave_system_id);
        $this->assertEquals($target->id, $file->fresh()->cave_system_id);
        $this->assertEquals($target->id, $edit->fresh()->suggestable_id);
        $this->assertTrue($target->fresh()->tags->contains($tag));
        $this->assertEquals(200, $target->fresh()->length);

        // Source deleted
        $this->assertDatabaseMissing('cave_systems', ['id' => $source->id]);
    }
}
