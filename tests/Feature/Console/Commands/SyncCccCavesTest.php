<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Cave;
use App\Models\SuggestedEdit;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncCccCavesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Http::fake([
            'cambriancavingcouncil.org.uk/*' => Http::response($this->getMockXml(), 200)
        ]);
        
        Tag::firstOrCreate(['tag' => 'cave'], ['type' => 'cave', 'category' => 'general']);
        Tag::firstOrCreate(['tag' => 'South Wales'], ['type' => 'cave', 'category' => 'region']);
    }

    private function getMockXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Registry>
    <Region name="South Wales">
        <Entry id="1" len="300" dep="50" alt="150" E="260000" N="215000" GR="SN">
            <Name>Test Cave One</Name>
            <Desc>A test cave description.</Desc>
            <Access con="Permit required">Permit Details</Access>
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
        $this->artisan('sync:ccc-caves --min-length=250')
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
        $this->artisan('sync:ccc-caves --min-length=250 --blocklist="Blocked Cave"')
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

        $this->artisan('sync:ccc-caves --min-length=250')
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

        $this->artisan('sync:ccc-caves --min-length=250')
            ->assertExitCode(0);

        $this->assertDatabaseCount('suggested_edits', 1);
        
        $edit = SuggestedEdit::first();
        $this->assertStringContainsString('A test cave description.', $edit->suggested_data['description']);
    }
}
