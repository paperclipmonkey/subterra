<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\SuggestedEdit;
use App\Models\Tag;
use App\Models\User;
use App\Services\Assistant\Tools\Admin\FindLinkCandidatesTool;
use App\Services\Assistant\Tools\Admin\ListTagsTool;
use App\Services\Assistant\Tools\Admin\ProposeBulkTagTool;
use App\Services\Assistant\Tools\Admin\ProposeDataFixTool;
use App\Services\Assistant\Tools\Admin\ProposeSystemMergeTool;
use App\Services\Assistant\Tools\Admin\ScanDataIssuesTool;
use App\Services\DataHealth\DataHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataStewardToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    // -------------------------------------------------------------------------
    // DataHealthService scanner
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function scanner_finds_systems_missing_length_or_depth(): void
    {
        $missing = CaveSystem::factory()->create(['name' => 'Gaping Void', 'length' => 0, 'vertical_range' => 0]);
        CaveSystem::factory()->create(['name' => 'Complete Cave', 'length' => 5000, 'vertical_range' => 120]);

        $service = app(DataHealthService::class);
        $results = $service->systemsMissingLengthDepth();

        $ids = array_column($results, 'cave_system_id');
        $this->assertContains($missing->id, $ids);
        $this->assertCount(1, $results);
        $this->assertEqualsCanonicalizing(['length', 'vertical_range'], $results[0]['missing']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function scanner_finds_caves_missing_region_tag(): void
    {
        $region = Tag::factory()->create(['tag' => 'Mendip', 'category' => 'region', 'type' => 'cave']);

        $tagged = Cave::factory()->create(['name' => 'Tagged Hole']);
        $tagged->tags()->attach($region->id);
        $untagged = Cave::factory()->create(['name' => 'Untagged Hole']);

        $results = app(DataHealthService::class)->cavesMissingRegionTag();

        $ids = array_column($results, 'cave_id');
        $this->assertContains($untagged->id, $ids);
        $this->assertNotContains($tagged->id, $ids);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function scanner_finds_unlinked_entrance_candidates_by_proximity(): void
    {
        $systemA = CaveSystem::factory()->create(['name' => 'Easegill System']);
        $systemB = CaveSystem::factory()->create(['name' => 'Easegill Caverns']);
        $systemFar = CaveSystem::factory()->create(['name' => 'Distant Cave']);

        // ~100m apart, different systems
        Cave::factory()->create(['cave_system_id' => $systemA->id, 'location_lat' => 54.2000, 'location_lng' => -2.5000]);
        Cave::factory()->create(['cave_system_id' => $systemB->id, 'location_lat' => 54.2009, 'location_lng' => -2.5000]);
        // 50km away
        Cave::factory()->create(['cave_system_id' => $systemFar->id, 'location_lat' => 53.7000, 'location_lng' => -2.5000]);

        $results = app(DataHealthService::class)->unlinkedEntranceCandidates();

        $this->assertCount(1, $results);
        $this->assertLessThan(400, $results[0]['distance_m']);
        $systems = [$results[0]['cave_a']['cave_system_id'], $results[0]['cave_b']['cave_system_id']];
        $this->assertEqualsCanonicalizing([$systemA->id, $systemB->id], $systems);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function find_link_candidates_returns_nearby_and_similar_named_systems(): void
    {
        $system = CaveSystem::factory()->create(['name' => 'Lancaster Hole']);
        Cave::factory()->create(['cave_system_id' => $system->id, 'location_lat' => 54.2000, 'location_lng' => -2.5000]);

        $nearby = CaveSystem::factory()->create(['name' => 'Lancaster Pot']);
        Cave::factory()->create(['cave_system_id' => $nearby->id, 'location_lat' => 54.2005, 'location_lng' => -2.5000]);

        $result = (new FindLinkCandidatesTool(app(DataHealthService::class)))
            ->handle(['cave_system_id' => $system->id], $this->admin);

        $this->assertSame($system->id, $result['cave_system_id']);
        $nearbyIds = array_column($result['nearby_systems'], 'cave_system_id');
        $this->assertContains($nearby->id, $nearbyIds);
        $similarIds = array_column($result['similar_name_systems'], 'cave_system_id');
        $this->assertContains($nearby->id, $similarIds);
    }

    // -------------------------------------------------------------------------
    // Scan + list tools
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function scan_tool_returns_summary_counts(): void
    {
        CaveSystem::factory()->create(['length' => 0, 'vertical_range' => 0]);

        $result = app(ScanDataIssuesTool::class)->handle(['issue_type' => 'summary'], $this->admin);

        $this->assertArrayHasKey('issue_counts', $result);
        $this->assertSame(1, $result['issue_counts']['missing_length_depth']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function list_tags_tool_returns_ids_and_usage_counts(): void
    {
        $tag = Tag::factory()->create(['tag' => 'Steward Test Tag', 'category' => 'curated', 'type' => 'cave']);
        $cave = Cave::factory()->create();
        $cave->tags()->attach($tag->id);

        $result = app(ListTagsTool::class)->handle([], $this->admin);

        $found = collect($result['tags'])->firstWhere('id', $tag->id);
        $this->assertNotNull($found);
        $this->assertSame('Steward Test Tag', $found['tag']);
        $this->assertSame(1, $found['cave_count']);
        $this->assertSame(0, $found['system_count']);
    }

    // -------------------------------------------------------------------------
    // ProposeDataFixTool
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function propose_data_fix_files_a_pip_suggested_edit_without_touching_live_data(): void
    {
        $system = CaveSystem::factory()->create(['name' => 'Gaping Void', 'length' => 0, 'vertical_range' => 0]);

        $result = app(ProposeDataFixTool::class)->handle([
            'target_type' => 'cave_system',
            'target_id' => $system->id,
            'changes' => ['length' => 4500, 'vertical_range' => 120],
            'reasoning' => 'Description states "4.5km of passage descending 120m".',
        ], $this->admin);

        $this->assertTrue($result['success']);

        $edit = SuggestedEdit::findOrFail($result['suggested_edit_id']);
        $this->assertSame('pip', $edit->source);
        $this->assertSame('pending', $edit->status);
        $this->assertSame($this->admin->id, $edit->user_id);
        $this->assertSame(['length' => 4500, 'vertical_range' => 120], $edit->suggested_data);
        $this->assertSame(['length' => 0, 'vertical_range' => 0], $edit->original_data);
        $this->assertStringContainsString('4.5km', $edit->reasoning);

        // Live data untouched
        $this->assertSame(0, $system->fresh()->length);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function propose_data_fix_rejects_disallowed_fields_and_bad_values(): void
    {
        $cave = Cave::factory()->create();
        $tool = app(ProposeDataFixTool::class);

        $disallowed = $tool->handle([
            'target_type' => 'cave',
            'target_id' => $cave->id,
            'changes' => ['registry' => 'hacked'],
            'reasoning' => 'x',
        ], $this->admin);
        $this->assertArrayHasKey('error', $disallowed);

        $nonNumeric = $tool->handle([
            'target_type' => 'cave',
            'target_id' => $cave->id,
            'changes' => ['length' => 'four thousand'],
            'reasoning' => 'x',
        ], $this->admin);
        $this->assertArrayHasKey('error', $nonNumeric);

        $missingReasoning = $tool->handle([
            'target_type' => 'cave',
            'target_id' => $cave->id,
            'changes' => ['length' => 4000],
            'reasoning' => '',
        ], $this->admin);
        $this->assertArrayHasKey('error', $missingReasoning);

        $this->assertSame(0, SuggestedEdit::count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function propose_data_fix_rejects_noop_changes(): void
    {
        $system = CaveSystem::factory()->create(['length' => 4500]);

        $result = app(ProposeDataFixTool::class)->handle([
            'target_type' => 'cave_system',
            'target_id' => $system->id,
            'changes' => ['length' => 4500],
            'reasoning' => 'Already correct.',
        ], $this->admin);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, SuggestedEdit::count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function propose_data_fix_can_relink_a_cave_to_another_system(): void
    {
        $cave = Cave::factory()->create();
        $newSystem = CaveSystem::factory()->create();

        $result = app(ProposeDataFixTool::class)->handle([
            'target_type' => 'cave',
            'target_id' => $cave->id,
            'changes' => ['cave_system_id' => $newSystem->id],
            'reasoning' => 'Entrance is 80m from the system and named after it.',
        ], $this->admin);

        $this->assertTrue($result['success']);
        $edit = SuggestedEdit::findOrFail($result['suggested_edit_id']);
        $this->assertSame($newSystem->id, $edit->suggested_data['cave_system_id']);
    }

    // -------------------------------------------------------------------------
    // ProposeBulkTagTool
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function propose_bulk_tag_files_one_edit_per_target_in_a_single_batch(): void
    {
        $curated = Tag::factory()->create(['tag' => 'Curated', 'category' => 'curated', 'type' => 'cave']);
        $caves = Cave::factory()->count(3)->create();

        $result = app(ProposeBulkTagTool::class)->handle([
            'cave_ids' => $caves->pluck('id')->all(),
            'add_tag_ids' => [$curated->id],
            'reasoning' => 'Popular UK caving trips per admin instruction.',
        ], $this->admin);

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['proposals_created']);

        $edits = SuggestedEdit::where('batch_id', $result['batch_id'])->get();
        $this->assertCount(3, $edits);
        $this->assertSame([$curated->id], $edits[0]->suggested_data['tags_add']);
        $this->assertSame(['Curated'], $edits[0]->suggested_data['tags_add_names']);

        // No live tags applied yet
        $this->assertSame(0, $caves[0]->tags()->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function propose_bulk_tag_skips_targets_that_already_have_the_tag(): void
    {
        $curated = Tag::factory()->create(['tag' => 'Curated', 'category' => 'curated', 'type' => 'cave']);
        $alreadyTagged = Cave::factory()->create();
        $alreadyTagged->tags()->attach($curated->id);
        $fresh = Cave::factory()->create();

        $result = app(ProposeBulkTagTool::class)->handle([
            'cave_ids' => [$alreadyTagged->id, $fresh->id],
            'add_tag_ids' => [$curated->id],
            'reasoning' => 'Curating.',
        ], $this->admin);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['proposals_created']);
        $this->assertContains($alreadyTagged->name, $result['skipped_already_correct']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function propose_bulk_tag_validates_tag_ids(): void
    {
        $cave = Cave::factory()->create();

        $result = app(ProposeBulkTagTool::class)->handle([
            'cave_ids' => [$cave->id],
            'add_tag_ids' => [99999],
            'reasoning' => 'x',
        ], $this->admin);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, SuggestedEdit::count());
    }

    // -------------------------------------------------------------------------
    // ProposeSystemMergeTool
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function propose_system_merge_files_a_merge_proposal(): void
    {
        $target = CaveSystem::factory()->create(['name' => 'Easegill System']);
        $source = CaveSystem::factory()->create(['name' => 'Easegill Caverns']);

        $result = app(ProposeSystemMergeTool::class)->handle([
            'target_system_id' => $target->id,
            'source_system_id' => $source->id,
            'reasoning' => 'Entrances 90m apart, names 85% similar.',
        ], $this->admin);

        $this->assertTrue($result['success']);
        $edit = SuggestedEdit::findOrFail($result['suggested_edit_id']);
        $this->assertSame($target->id, $edit->suggestable_id);
        $this->assertSame($source->id, $edit->suggested_data['merge_source_system_id']);

        // Both systems still exist — nothing applied yet
        $this->assertNotNull($source->fresh());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function propose_system_merge_blocks_duplicates_and_self_merge(): void
    {
        $target = CaveSystem::factory()->create();
        $source = CaveSystem::factory()->create();
        $tool = app(ProposeSystemMergeTool::class);

        $self = $tool->handle([
            'target_system_id' => $target->id,
            'source_system_id' => $target->id,
            'reasoning' => 'x',
        ], $this->admin);
        $this->assertArrayHasKey('error', $self);

        $first = $tool->handle([
            'target_system_id' => $target->id,
            'source_system_id' => $source->id,
            'reasoning' => 'x',
        ], $this->admin);
        $this->assertTrue($first['success']);

        $duplicate = $tool->handle([
            'target_system_id' => $target->id,
            'source_system_id' => $source->id,
            'reasoning' => 'x',
        ], $this->admin);
        $this->assertArrayHasKey('error', $duplicate);
    }
}
