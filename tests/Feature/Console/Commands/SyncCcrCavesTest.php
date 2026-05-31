<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\SuggestedEdit;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncCcrCavesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'cambriancavingcouncil.org.uk/*' => Http::response($this->getMockXml(), 200),
        ]);

        Tag::firstOrCreate(['tag' => 'Cave', 'category' => 'type'], ['type' => 'cave']);
        Tag::firstOrCreate(['tag' => 'South Wales', 'category' => 'region'], ['type' => 'cave']);
    }

    private function getMockXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Registry>
    <Region name="South Wales">
        <Entry id="1" len="300" dep="50" alt="150" E="260000" N="215000" GR="SN">
            <Name>Test Cave One</Name>
            <Desc>A test cave description.</Desc>
            <Access con="loc">Entry through <a href="http://example.com">Ogof Gam</a>. See caving.wales for details</Access>
            <Bibl>Welsh Cave Guide, 1st Edition</Bibl>
            <Bibl>Caves of South Wales, 2005</Bibl>
        </Entry>
        <Entry id="2" len="100" dep="10" alt="200" E="261000" N="216000" GR="SN">
            <Name>Short Cave</Name>
            <Desc>Too short to import normally.</Desc>
        </Entry>
        <Entry id="3" len="500" dep="100" alt="300" E="262000" N="217000" GR="SN">
            <Name>Blocked Cave</Name>
            <Desc>This cave is blocked.</Desc>
        </Entry>
    </Region>
</Registry>
XML;
    }

    public function test_it_imports_new_caves_that_meet_criteria()
    {
        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', [
            'name' => 'Test Cave One',
        ]);

        $this->assertDatabaseMissing('caves', [
            'name' => 'Short Cave',
        ]);

        $this->assertDatabaseHas('caves', [
            'name' => 'Blocked Cave',
        ]);

        $this->assertDatabaseCount('suggested_edits', 0);
    }

    public function test_it_respects_the_blocklist()
    {
        $this->artisan('sync:ccr-caves --min-length=250 --blocklist="Blocked Cave"')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', [
            'name' => 'Test Cave One',
        ]);

        $this->assertDatabaseMissing('caves', [
            'name' => 'Blocked Cave',
        ]);
    }

    public function test_it_creates_suggested_edit_for_existing_cave_with_differences()
    {
        $cave = Cave::factory()->create([
            'name' => 'Test Cave One',
            'description' => 'Old description',
            'location_lat' => 51.0,
            'location_lng' => -3.0,
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        // Cave should not be directly updated with new description
        $this->assertDatabaseHas('caves', [
            'id' => $cave->id,
            'description' => 'Old description',
        ]);

        // Suggested edit should be created
        $this->assertDatabaseHas('suggested_edits', [
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'status' => 'pending',
        ]);

        $edit = SuggestedEdit::where('suggestable_id', $cave->id)->first();
        $this->assertNull($edit->user_id);
        $this->assertArrayHasKey('description', $edit->suggested_data);
        $this->assertStringContainsString('A test cave description.', $edit->suggested_data['description']);
    }

    public function test_it_updates_existing_pending_suggested_edit()
    {
        $cave = Cave::factory()->create([
            'name' => 'Test Cave One',
            'description' => 'Old description',
            'location_lat' => 51.0,
            'location_lng' => -3.0,
        ]);

        SuggestedEdit::create([
            'user_id' => null,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => ['description' => 'Old description'],
            'suggested_data' => ['description' => 'Some other intermediate description'],
            'status' => 'pending',
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        $this->assertDatabaseCount('suggested_edits', 1);

        $edit = SuggestedEdit::first();
        $this->assertStringContainsString('A test cave description.', $edit->suggested_data['description']);
    }

    public function test_it_skips_description_when_ccr_text_already_in_subterra_text()
    {
        // Subterra has an expanded description that already contains the CCR text
        $cave = Cave::factory()->create([
            'name' => 'Test Cave One',
            'description' => "A test cave description.\n\nCC Registry: https://www.cambriancavingcouncil.org.uk/registry/ccr_registry_view.php?ID=1\n\nAdditional Subterra notes about the cave history and geology.",
            'location_name' => 'South Wales',
            'location_country' => 'United Kingdom',
            'access_info' => 'Entry through Ogof Gam. See caving.wales for details',
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        // Description should NOT appear in suggested edits because CCR text
        // is already contained within the longer Subterra text
        $edit = SuggestedEdit::where('suggestable_id', $cave->id)->first();
        if ($edit) {
            $this->assertArrayNotHasKey('description', $edit->suggested_data);
        }
    }

    public function test_it_skips_description_when_ccr_link_has_different_formatting()
    {
        // Subterra has the CCR link wrapped in angle brackets (from a previous import)
        $cave = Cave::factory()->create([
            'name' => 'Test Cave One',
            'description' => "Extra intro text. A test cave description.\n\nCC Registry: <https://www.cambriancavingcouncil.org.uk/registry/ccr_registry_view.php?ID=1> <br />",
            'location_name' => 'South Wales',
            'location_country' => 'United Kingdom',
            'access_info' => 'Entry through Ogof Gam. See caving.wales for details',
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        // Description should NOT appear because the Desc text is found and
        // the CC registry ID is already referenced (even with different formatting)
        $edit = SuggestedEdit::where('suggestable_id', $cave->id)->first();
        if ($edit) {
            $this->assertArrayNotHasKey('description', $edit->suggested_data);
        }
    }

    public function test_it_suggests_description_when_ccr_text_not_in_subterra_text()
    {
        // Subterra has a completely different description
        $cave = Cave::factory()->create([
            'name' => 'Test Cave One',
            'description' => 'A completely different description written by someone else.',
            'location_lat' => 51.0,
            'location_lng' => -3.0,
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        $edit = SuggestedEdit::where('suggestable_id', $cave->id)->first();
        $this->assertNotNull($edit);
        $this->assertArrayHasKey('description', $edit->suggested_data);
    }

    public function test_it_skips_access_info_when_already_contained()
    {
        $cave = Cave::factory()->create([
            'name' => 'Test Cave One',
            'description' => "A test cave description.\n\nCC Registry: https://www.cambriancavingcouncil.org.uk/registry/ccr_registry_view.php?ID=1",
            'location_name' => 'South Wales',
            'location_country' => 'United Kingdom',
            'access_info' => 'Entry through Ogof Gam. See caving.wales for details. Contact the landowner for access.',
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        $edit = SuggestedEdit::where('suggestable_id', $cave->id)->first();
        if ($edit) {
            $this->assertArrayNotHasKey('access_info', $edit->suggested_data);
        }
    }

    public function test_it_handles_merged_cave_with_existing_name()
    {
        // Simulate a merged entrance: the cave exists under the same name
        // but belongs to a different cave system (merged)
        $cave = Cave::factory()->create([
            'name' => 'Test Cave One',
            'description' => 'Original entrance description',
            'location_lat' => 51.0,
            'location_lng' => -3.0,
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        // The sync should find by name and create a suggested edit,
        // not create a duplicate cave
        $this->assertEquals(1, Cave::where('name', 'Test Cave One')->count());

        $edit = SuggestedEdit::where('suggestable_id', $cave->id)->first();
        $this->assertNotNull($edit);
        $this->assertNull($edit->user_id);
    }

    public function test_it_finds_existing_cave_by_slug_fallback()
    {
        // Cave has a different name but matches the generated slug
        $cave = Cave::factory()->create([
            'name' => 'Test Cave One (Main Entrance)',
            'slug' => 'south_wales_test-cave-one',
            'description' => 'Original description',
            'location_lat' => 51.0,
            'location_lng' => -3.0,
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        // Should match by slug and not create a duplicate with the import name
        $this->assertDatabaseMissing('caves', [
            'name' => 'Test Cave One',
        ]);
        // The original cave should still exist
        $this->assertDatabaseHas('caves', [
            'id' => $cave->id,
            'name' => 'Test Cave One (Main Entrance)',
        ]);
    }

    public function test_it_does_not_suggest_empty_text_fields()
    {
        Http::fake([
            'cambriancavingcouncil.org.uk/*' => Http::response($this->getXmlWithEmptyDesc(), 200),
        ]);

        $cave = Cave::factory()->create([
            'name' => 'Empty Desc Cave',
            'description' => 'Subterra has a description',
            'location_name' => 'South Wales',
            'location_country' => 'United Kingdom',
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        $edit = SuggestedEdit::where('suggestable_id', $cave->id)->first();
        if ($edit) {
            $this->assertArrayNotHasKey('description', $edit->suggested_data);
        }
    }

    private function getXmlWithEmptyDesc(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Registry>
    <Region name="South Wales">
        <Entry id="10" len="300" dep="50" alt="150" E="260000" N="215000" GR="SN">
            <Name>Empty Desc Cave</Name>
            <Desc></Desc>
        </Entry>
    </Region>
</Registry>
XML;
    }

    public function test_new_cave_system_gets_references_as_markdown_list()
    {
        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        $system = CaveSystem::where('name', 'Test Cave One')->first();
        $this->assertNotNull($system);
        $this->assertEquals("- [CC Registry](https://www.cambriancavingcouncil.org.uk/registry/ccr_registry_view.php?ID=1)\n- Welsh Cave Guide, 1st Edition\n- Caves of South Wales, 2005", $system->references);
    }

    public function test_existing_system_references_create_suggested_edit_not_direct_update()
    {
        $system = CaveSystem::create([
            'name' => 'Test Cave One',
            'slug' => 'test-cave-one',
            'length' => 0,
            'vertical_range' => 0,
            'references' => '- My own reference',
        ]);

        Cave::factory()->create([
            'name' => 'Test Cave One',
            'cave_system_id' => $system->id,
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        // References should NOT be directly modified
        $system->refresh();
        $this->assertEquals('- My own reference', $system->references);

        // A suggested edit should be created for the CaveSystem
        $edit = SuggestedEdit::where('suggestable_type', CaveSystem::class)
            ->where('suggestable_id', $system->id)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($edit);
        $this->assertArrayHasKey('references', $edit->suggested_data);
        $this->assertStringContainsString('- [CC Registry](https://www.cambriancavingcouncil.org.uk/registry/ccr_registry_view.php?ID=1)', $edit->suggested_data['references']);
        $this->assertStringContainsString('- Welsh Cave Guide, 1st Edition', $edit->suggested_data['references']);
        $this->assertStringContainsString('- My own reference', $edit->suggested_data['references']);
    }

    public function test_existing_system_with_same_references_does_not_duplicate()
    {
        $system = CaveSystem::create([
            'name' => 'Test Cave One',
            'slug' => 'test-cave-one',
            'length' => 0,
            'vertical_range' => 0,
            'references' => "- [CC Registry](https://www.cambriancavingcouncil.org.uk/registry/ccr_registry_view.php?ID=1)\n- Welsh Cave Guide, 1st Edition\n- Caves of South Wales, 2005",
        ]);

        Cave::factory()->create([
            'name' => 'Test Cave One',
            'cave_system_id' => $system->id,
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        // No suggested edit should be created — references already match
        $this->assertDatabaseMissing('suggested_edits', [
            'suggestable_type' => CaveSystem::class,
            'suggestable_id' => $system->id,
        ]);

        // References should remain unchanged
        $system->refresh();
        $this->assertEquals("- [CC Registry](https://www.cambriancavingcouncil.org.uk/registry/ccr_registry_view.php?ID=1)\n- Welsh Cave Guide, 1st Edition\n- Caves of South Wales, 2005", $system->references);
    }

    public function test_existing_system_references_without_list_prefix_still_match()
    {
        // References stored without markdown list prefix (legacy format)
        $system = CaveSystem::create([
            'name' => 'Test Cave One',
            'slug' => 'test-cave-one',
            'length' => 0,
            'vertical_range' => 0,
            'references' => "[CC Registry](https://www.cambriancavingcouncil.org.uk/registry/ccr_registry_view.php?ID=1)\nWelsh Cave Guide, 1st Edition\nCaves of South Wales, 2005",
        ]);

        Cave::factory()->create([
            'name' => 'Test Cave One',
            'cave_system_id' => $system->id,
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        // Should not create a suggested edit since the references match after normalization
        $this->assertDatabaseMissing('suggested_edits', [
            'suggestable_type' => CaveSystem::class,
            'suggestable_id' => $system->id,
        ]);
    }

    public function test_duplicate_xml_bibl_entries_are_deduplicated()
    {
        // XML has duplicate Bibl entries (CCR data can contain these)
        Http::fake([
            'cambriancavingcouncil.org.uk/*' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Registry>
    <Region name="South Wales">
        <Entry id="1" len="300" dep="50" alt="150" E="260000" N="215000" GR="SN">
            <Name>Test Cave One</Name>
            <Desc>A test cave description.</Desc>
            <Bibl>Welsh Cave Guide, 1st Edition</Bibl>
            <Bibl>Welsh Cave Guide, 1st Edition</Bibl>
            <Bibl>Caves of South Wales, 2005</Bibl>
        </Entry>
    </Region>
</Registry>
XML, 200),
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        $system = CaveSystem::where('name', 'Test Cave One')->first();
        // Should only have unique references (CCR link + 2 bibl entries)
        $this->assertEquals("- [CC Registry](https://www.cambriancavingcouncil.org.uk/registry/ccr_registry_view.php?ID=1)\n- Welsh Cave Guide, 1st Edition\n- Caves of South Wales, 2005", $system->references);
    }

    public function test_existing_references_in_concatenated_line_are_not_added_again()
    {
        // Legacy data: references stored as concatenated text without newlines
        $system = CaveSystem::create([
            'name' => 'Test Cave One',
            'slug' => 'test-cave-one',
            'length' => 0,
            'vertical_range' => 0,
            'references' => 'Other Ref [CC Registry](https://www.cambriancavingcouncil.org.uk/registry/ccr_registry_view.php?ID=1) Welsh Cave Guide, 1st Edition Caves of South Wales, 2005',
        ]);

        Cave::factory()->create([
            'name' => 'Test Cave One',
            'cave_system_id' => $system->id,
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        // Should not create a suggested edit because both refs already appear in the existing text
        $this->assertDatabaseMissing('suggested_edits', [
            'suggestable_type' => CaveSystem::class,
            'suggestable_id' => $system->id,
        ]);
    }

    public function test_existing_system_with_empty_references_creates_suggestion_not_direct_update()
    {
        $system = CaveSystem::create([
            'name' => 'Test Cave One',
            'slug' => 'test-cave-one',
            'length' => 0,
            'vertical_range' => 0,
            'references' => null,
        ]);

        Cave::factory()->create([
            'name' => 'Test Cave One',
            'cave_system_id' => $system->id,
        ]);

        $this->artisan('sync:ccr-caves --min-length=250')
            ->assertExitCode(0);

        // References should NOT be directly modified
        $system->refresh();
        $this->assertNull($system->references);

        // A suggested edit should be created for the CaveSystem
        $edit = SuggestedEdit::where('suggestable_type', CaveSystem::class)
            ->where('suggestable_id', $system->id)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($edit);
        $this->assertArrayHasKey('references', $edit->suggested_data);
        $this->assertStringContainsString('- [CC Registry](https://www.cambriancavingcouncil.org.uk/registry/ccr_registry_view.php?ID=1)', $edit->suggested_data['references']);
        $this->assertStringContainsString('- Welsh Cave Guide, 1st Edition', $edit->suggested_data['references']);
    }
}
