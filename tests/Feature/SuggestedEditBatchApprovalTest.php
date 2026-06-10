<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\SuggestedEdit;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SuggestedEditBatchApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function makeTagProposal(Cave $cave, Tag $tag, ?string $batchId = 'pip-test-batch'): SuggestedEdit
    {
        return SuggestedEdit::create([
            'user_id' => $this->admin->id,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => ['tags' => []],
            'suggested_data' => [
                'tags_add' => [$tag->id],
                'tags_add_names' => [$tag->tag],
            ],
            'status' => 'pending',
            'source' => 'pip',
            'batch_id' => $batchId,
            'reasoning' => 'Popular UK trip.',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function approving_a_tag_proposal_applies_the_tags(): void
    {
        Mail::fake();

        $tag = Tag::factory()->create(['tag' => 'Curated', 'category' => 'curated']);
        $cave = Cave::factory()->create();
        $edit = $this->makeTagProposal($cave, $tag, null);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/suggested-edits/{$edit->id}/approve")
            ->assertStatus(200);

        $this->assertSame('approved', $edit->fresh()->status);
        $this->assertTrue($cave->tags()->where('tags.id', $tag->id)->exists());

        // Pip-sourced approvals should not email the proposing admin
        Mail::assertNothingSent();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function approving_a_tag_removal_proposal_detaches_the_tag(): void
    {
        $tag = Tag::factory()->create(['tag' => 'Curated', 'category' => 'curated']);
        $cave = Cave::factory()->create();
        $cave->tags()->attach($tag->id);

        $edit = SuggestedEdit::create([
            'user_id' => $this->admin->id,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => ['tags' => [$tag->tag]],
            'suggested_data' => ['tags_remove' => [$tag->id], 'tags_remove_names' => [$tag->tag]],
            'status' => 'pending',
            'source' => 'pip',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/suggested-edits/{$edit->id}/approve")
            ->assertStatus(200);

        $this->assertFalse($cave->tags()->where('tags.id', $tag->id)->exists());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function batches_endpoint_lists_pending_batches(): void
    {
        $tag = Tag::factory()->create(['tag' => 'Curated', 'category' => 'curated']);
        $caves = Cave::factory()->count(2)->create();
        foreach ($caves as $cave) {
            $this->makeTagProposal($cave, $tag);
        }

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/suggested-edits/batches')
            ->assertStatus(200);

        $batches = $response->json('batches');
        $this->assertCount(1, $batches);
        $this->assertSame('pip-test-batch', $batches[0]['batch_id']);
        $this->assertSame(2, $batches[0]['count']);
        $this->assertSame('pip', $batches[0]['source']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function batch_approve_applies_every_edit_in_the_batch(): void
    {
        Mail::fake();

        $tag = Tag::factory()->create(['tag' => 'Curated', 'category' => 'curated']);
        $caves = Cave::factory()->count(3)->create();
        foreach ($caves as $cave) {
            $this->makeTagProposal($cave, $tag);
        }

        $this->actingAs($this->admin)
            ->postJson('/api/admin/suggested-edits/batches/pip-test-batch/approve')
            ->assertStatus(200)
            ->assertJsonPath('approved', 3);

        foreach ($caves as $cave) {
            $this->assertTrue($cave->tags()->where('tags.id', $tag->id)->exists());
        }
        $this->assertSame(3, SuggestedEdit::where('status', 'approved')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function batch_reject_rejects_every_edit_without_applying(): void
    {
        $tag = Tag::factory()->create(['tag' => 'Curated', 'category' => 'curated']);
        $caves = Cave::factory()->count(2)->create();
        foreach ($caves as $cave) {
            $this->makeTagProposal($cave, $tag);
        }

        $this->actingAs($this->admin)
            ->postJson('/api/admin/suggested-edits/batches/pip-test-batch/reject', [
                'admin_comment' => 'Not these caves.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('rejected', 2);

        foreach ($caves as $cave) {
            $this->assertSame(0, $cave->tags()->count());
        }
        $this->assertSame(2, SuggestedEdit::where('status', 'rejected')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function approving_a_merge_proposal_merges_the_systems(): void
    {
        $target = CaveSystem::factory()->create(['name' => 'Easegill System', 'length' => 5000, 'vertical_range' => 100]);
        $source = CaveSystem::factory()->create(['name' => 'Easegill Caverns', 'length' => 8000, 'vertical_range' => 50]);
        $sourceCave = Cave::factory()->create(['cave_system_id' => $source->id]);

        $edit = SuggestedEdit::create([
            'user_id' => $this->admin->id,
            'suggestable_type' => CaveSystem::class,
            'suggestable_id' => $target->id,
            'original_data' => [],
            'suggested_data' => [
                'merge_source_system_id' => $source->id,
                'merge_source_system_name' => $source->name,
            ],
            'status' => 'pending',
            'source' => 'pip',
            'reasoning' => 'Same system, imported twice.',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/suggested-edits/{$edit->id}/approve")
            ->assertStatus(200);

        $this->assertNull(CaveSystem::find($source->id));
        $this->assertSame($target->id, $sourceCave->fresh()->cave_system_id);
        // Merge keeps the larger of the two lengths
        $this->assertSame(8000, $target->fresh()->length);
        $this->assertSame('approved', $edit->fresh()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function index_supports_batch_and_source_filters(): void
    {
        $tag = Tag::factory()->create(['tag' => 'Curated', 'category' => 'curated']);
        $cave = Cave::factory()->create();
        $this->makeTagProposal($cave, $tag);

        // A regular user-sourced edit outside the batch
        SuggestedEdit::create([
            'user_id' => $this->admin->id,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => [],
            'suggested_data' => ['length' => 100],
            'status' => 'pending',
        ]);

        $byBatch = $this->actingAs($this->admin)
            ->getJson('/api/admin/suggested-edits?status=pending&batch=pip-test-batch')
            ->assertStatus(200)
            ->json('data');
        $this->assertCount(1, $byBatch);

        $bySource = $this->actingAs($this->admin)
            ->getJson('/api/admin/suggested-edits?status=pending&source=pip')
            ->assertStatus(200)
            ->json('data');
        $this->assertCount(1, $bySource);
        $this->assertSame('pip', $bySource[0]['source']);
    }
}
