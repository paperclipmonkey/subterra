<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\SuggestedEdit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixCcrSyncArtifactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_decodes_html_entities_in_ccr_cave_text()
    {
        $cave = Cave::factory()->create([
            'registry' => 'ccr',
            'registry_id' => '1',
            'access_info' => 'The entrance is capped &amp; gated.',
            'description' => 'A cave with &lt;5m crawl &amp; a sump.',
        ]);

        $this->artisan('caves:fix-ccr-sync-artifacts')->assertExitCode(0);

        $cave->refresh();
        $this->assertEquals('The entrance is capped & gated.', $cave->access_info);
        $this->assertEquals('A cave with <5m crawl & a sump.', $cave->description);
    }

    public function test_it_decodes_entities_in_linked_cave_systems()
    {
        $system = CaveSystem::create([
            'name' => 'Test System',
            'slug' => 'test-system',
            'length' => 0,
            'vertical_range' => 0,
            'references' => '- Survey &amp; description, 1999',
        ]);

        Cave::factory()->create([
            'registry' => 'ccr',
            'registry_id' => '2',
            'cave_system_id' => $system->id,
        ]);

        $this->artisan('caves:fix-ccr-sync-artifacts')->assertExitCode(0);

        $system->refresh();
        $this->assertEquals('- Survey & description, 1999', $system->references);
    }

    public function test_it_does_not_touch_non_ccr_caves()
    {
        $cave = Cave::factory()->create([
            'registry' => null,
            'access_info' => 'Left as &amp; is.',
        ]);

        $this->artisan('caves:fix-ccr-sync-artifacts')->assertExitCode(0);

        $cave->refresh();
        $this->assertEquals('Left as &amp; is.', $cave->access_info);
    }

    public function test_it_deletes_pending_bot_edit_that_is_entirely_no_op()
    {
        $cave = Cave::factory()->create(['access_info' => 'capped & gated']);

        $edit = SuggestedEdit::create([
            'user_id' => null,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => ['access_info' => "capped & gated\r\nsecond line"],
            'suggested_data' => ['access_info' => "capped &amp; gated\nsecond line"],
            'status' => 'pending',
        ]);

        $this->artisan('caves:fix-ccr-sync-artifacts')->assertExitCode(0);

        $this->assertDatabaseMissing('suggested_edits', ['id' => $edit->id]);
    }

    public function test_it_trims_no_op_field_but_keeps_real_change()
    {
        $cave = Cave::factory()->create();

        $edit = SuggestedEdit::create([
            'user_id' => null,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => ['access_info' => 'capped & gated', 'description' => 'Old description'],
            'suggested_data' => ['access_info' => 'capped &amp; gated', 'description' => 'A genuinely new description'],
            'status' => 'pending',
        ]);

        $this->artisan('caves:fix-ccr-sync-artifacts')->assertExitCode(0);

        $edit->refresh();
        $this->assertArrayNotHasKey('access_info', $edit->suggested_data);
        $this->assertArrayHasKey('description', $edit->suggested_data);
        $this->assertEquals('A genuinely new description', $edit->suggested_data['description']);
        $this->assertArrayNotHasKey('access_info', $edit->original_data);
    }

    public function test_it_does_not_touch_human_authored_edits()
    {
        $cave = Cave::factory()->create();
        $user = \App\Models\User::factory()->create();

        $edit = SuggestedEdit::create([
            'user_id' => $user->id,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => ['access_info' => 'capped & gated'],
            'suggested_data' => ['access_info' => 'capped &amp; gated'],
            'status' => 'pending',
        ]);

        $this->artisan('caves:fix-ccr-sync-artifacts')->assertExitCode(0);

        $this->assertDatabaseHas('suggested_edits', ['id' => $edit->id]);
    }

    public function test_dry_run_writes_nothing()
    {
        $cave = Cave::factory()->create([
            'registry' => 'ccr',
            'registry_id' => '3',
            'access_info' => 'capped &amp; gated',
        ]);

        $edit = SuggestedEdit::create([
            'user_id' => null,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => ['access_info' => 'capped & gated'],
            'suggested_data' => ['access_info' => 'capped &amp; gated'],
            'status' => 'pending',
        ]);

        $this->artisan('caves:fix-ccr-sync-artifacts --dry-run')->assertExitCode(0);

        $cave->refresh();
        $this->assertEquals('capped &amp; gated', $cave->access_info);
        $this->assertDatabaseHas('suggested_edits', ['id' => $edit->id]);
    }
}
