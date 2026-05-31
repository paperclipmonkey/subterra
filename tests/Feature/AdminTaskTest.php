<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_tasks()
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        // Create a "Good" system for the unrelated caves to belong to, so they don't pollute system lists
        $goodSystem = CaveSystem::factory()->create(['references' => 'Valid Refs', 'name' => 'Good System']);
        \App\Models\CaveSystemFile::create([
            'cave_system_id' => $goodSystem->id,
            'filename' => 'file.pdf',
            'original_filename' => 'file.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'details' => 'Test file',
        ]);

        // 1. Cave with missing photos (both hero and entrance)
        Cave::factory()->create(['description' => 'Desc', 'name' => 'No Photo Cave', 'cave_system_id' => $goodSystem->id]);

        // Cave with hero image but no entrance image - should NOT appear in missing photos
        $heroCave = Cave::factory()->create(['description' => 'Desc', 'name' => 'Hero Photo Cave', 'cave_system_id' => $goodSystem->id]);
        $heroCave->media()->create(['type' => 'hero', 'filename' => 'photo.jpg']);

        // Cave with entrance image but no hero image - should NOT appear in missing photos
        $entranceCave = Cave::factory()->create(['description' => 'Desc', 'name' => 'Entrance Photo Cave', 'cave_system_id' => $goodSystem->id]);
        $entranceCave->media()->create(['type' => 'entrance', 'filename' => 'entrance.jpg']);

        // Cave with both photos - should NOT appear in missing photos
        $bothCave = Cave::factory()->create(['description' => 'Desc', 'name' => 'Both Photos Cave', 'cave_system_id' => $goodSystem->id]);
        $bothCave->media()->create(['type' => 'hero', 'filename' => 'photo.jpg']);
        $bothCave->media()->create(['type' => 'entrance', 'filename' => 'entrance.jpg']);

        // 2. Cave with missing description
        $noDescCave = Cave::factory()->create(['description' => null, 'name' => 'No Desc Cave', 'cave_system_id' => $goodSystem->id]);
        $noDescCave->media()->create(['type' => 'hero', 'filename' => 'img.jpg']);

        $descCave = Cave::factory()->create(['description' => 'Has description', 'name' => 'Desc Cave', 'cave_system_id' => $goodSystem->id]);
        $descCave->media()->create(['type' => 'hero', 'filename' => 'img.jpg']);

        // 3. Cave with low tags (< 3)
        // Ensure they have photos/desc so they don't pollute other lists
        $lowTagsCave = Cave::factory()->create(['name' => 'Low Tags Cave', 'description' => 'Desc', 'cave_system_id' => $goodSystem->id]);
        $lowTagsCave->media()->create(['type' => 'hero', 'filename' => 'img.jpg']);
        $tag = Tag::factory()->create();
        $lowTagsCave->tags()->attach($tag); // 1 tag

        $highTagsCave = Cave::factory()->create(['name' => 'High Tags Cave', 'description' => 'Desc', 'cave_system_id' => $goodSystem->id]);
        $highTagsCave->media()->create(['type' => 'hero', 'filename' => 'img.jpg']);
        $tags = Tag::factory()->count(3)->create();
        $highTagsCave->tags()->attach($tags); // 3 tags

        // 4. System with missing references
        $noRefSys = CaveSystem::factory()->create(['references' => null, 'name' => 'No Ref System']);
        \App\Models\CaveSystemFile::create([
            'cave_system_id' => $noRefSys->id,
            'filename' => 'file.pdf',
            'original_filename' => 'file.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'details' => 'Test file',
        ]); // Has file, but no refs

        $refSys = CaveSystem::factory()->create(['references' => 'Book A', 'name' => 'Ref System']);
        \App\Models\CaveSystemFile::create([
            'cave_system_id' => $refSys->id,
            'filename' => 'file.pdf',
            'original_filename' => 'file.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'details' => 'Test file',
        ]); // Has file and refs

        // 5. System with missing files
        CaveSystem::factory()->create(['name' => 'No File System', 'references' => 'Refs']); // Has refs, but no file

        $response = $this->actingAs($admin)->getJson('/api/admin/tasks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'caves_no_photo',
                'caves_no_description',
                'caves_low_tags',
                'systems_no_references',
                'systems_no_files',
            ]);

        // Check No Photo
        $this->assertCount(1, $response->json('caves_no_photo'));
        $this->assertEquals('No Photo Cave', $response->json('caves_no_photo.0.name'));

        // Check No Desc
        $this->assertCount(1, $response->json('caves_no_description'));
        $this->assertEquals('No Desc Cave', $response->json('caves_no_description.0.name'));

        // Check Low Tags
        // Should contain 'Low Tags Cave' (1 tag) and 'No Photo Cave' (0 tags) and 'No Desc Cave' (0 tags)...
        // Actually factory caves have 0 tags by default.
        // So 'No Photo Cave' and 'No Desc Cave' also appear in Low Tags unless we add tags to them.
        // Let's just check 'Low Tags Cave' is present.
        $lowTagsNames = collect($response->json('caves_low_tags'))->pluck('name');
        $this->assertTrue($lowTagsNames->contains('Low Tags Cave'));
        $this->assertFalse($lowTagsNames->contains('High Tags Cave'));

        // Check No Refs
        $this->assertCount(1, $response->json('systems_no_references'));
        $this->assertEquals('No Ref System', $response->json('systems_no_references.0.name'));

        // Check No Files
        $this->assertTrue(collect($response->json('systems_no_files'))->pluck('name')->contains('No File System'));
    }
}
