<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuzzySearchCavesTest extends TestCase
{
    use RefreshDatabase;

    private Tag $curatedTag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->curatedTag = Tag::where('tag', 'Curated')->where('category', 'curated')->firstOrFail();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tool_search_finds_cave_system_by_exact_name(): void
    {
        $user = User::factory()->create();
        $system = CaveSystem::factory()->create(['name' => 'Swildon\'s Hole']);
        $system->tags()->attach($this->curatedTag->id);
        Cave::factory()->create(['cave_system_id' => $system->id, 'name' => 'Swildon\'s Hole']);

        // Using the AssistantService directly to test the tool
        $tool = app(\App\Services\Assistant\Tools\SearchCavesTool::class);
        $result = $tool->handle(['name' => 'Swildon\'s Hole'], $user);

        $this->assertNotEmpty($result['cave_systems']);
        $this->assertStringContainsString('Swildon', $result['cave_systems'][0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tool_search_finds_cave_system_by_name_without_apostrophe(): void
    {
        $user = User::factory()->create();
        $system = CaveSystem::factory()->create(['name' => 'Swildon\'s Hole']);
        $system->tags()->attach($this->curatedTag->id);
        Cave::factory()->create(['cave_system_id' => $system->id]);

        $tool = app(\App\Services\Assistant\Tools\SearchCavesTool::class);
        $result = $tool->handle(['name' => 'Swildons'], $user);

        $this->assertNotEmpty($result['cave_systems']);
        $this->assertStringContainsString('Swildon', $result['cave_systems'][0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tool_search_finds_cave_system_by_name_with_extra_apostrophe(): void
    {
        $user = User::factory()->create();
        $system = CaveSystem::factory()->create(['name' => 'Swildons Hole']);
        $system->tags()->attach($this->curatedTag->id);
        Cave::factory()->create(['cave_system_id' => $system->id]);

        $tool = app(\App\Services\Assistant\Tools\SearchCavesTool::class);
        $result = $tool->handle(['name' => 'Swildon\'s'], $user);

        $this->assertNotEmpty($result['cave_systems']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tool_search_handles_names_with_spacing_variations(): void
    {
        $user = User::factory()->create();
        $system = CaveSystem::factory()->create(['name' => 'Ogof  Ffynnon  Ddu']);
        $system->tags()->attach($this->curatedTag->id);
        Cave::factory()->create(['cave_system_id' => $system->id]);

        $tool = app(\App\Services\Assistant\Tools\SearchCavesTool::class);
        $result = $tool->handle(['name' => 'Ogof Ffynnon Ddu'], $user);

        $this->assertNotEmpty($result['cave_systems']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tool_search_is_case_insensitive(): void
    {
        $user = User::factory()->create();
        $system = CaveSystem::factory()->create(['name' => 'Swildon\'s Hole']);
        $system->tags()->attach($this->curatedTag->id);
        Cave::factory()->create(['cave_system_id' => $system->id]);

        $tool = app(\App\Services\Assistant\Tools\SearchCavesTool::class);
        $result = $tool->handle(['name' => 'SWILDONS'], $user);

        $this->assertNotEmpty($result['cave_systems']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tool_name_search_bypasses_curated_filter(): void
    {
        $user = User::factory()->create();
        // Create an uncurated system (no Curated tag)
        $system = CaveSystem::factory()->create(['name' => 'Obscure Cave']);
        Cave::factory()->create(['cave_system_id' => $system->id]);

        $tool = app(\App\Services\Assistant\Tools\SearchCavesTool::class);
        $result = $tool->handle(['name' => 'Obscure'], $user);

        // Should still find it even though it's not curated
        $this->assertNotEmpty($result['cave_systems']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tool_search_returns_empty_for_nonexistent_cave(): void
    {
        $user = User::factory()->create();

        $tool = app(\App\Services\Assistant\Tools\SearchCavesTool::class);
        $result = $tool->handle(['name' => 'Totally Fake Cave XYZ'], $user);

        $this->assertEmpty($result['cave_systems']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tool_search_finds_system_by_region_tag_on_cave(): void
    {
        $user = User::factory()->create();
        $system = CaveSystem::factory()->create(['name' => 'Otter Hole']);
        $system->tags()->attach($this->curatedTag->id);

        // Region tags live at the cave (entrance) level, not the system level.
        $region = Tag::create(['tag' => 'Forest of Dean', 'type' => 'cave', 'category' => 'region']);
        $cave = Cave::factory()->create([
            'cave_system_id' => $system->id,
            'name' => 'Otter Hole',
            'location_name' => 'Chepstow',
        ]);
        $cave->tags()->attach($region->id);

        $tool = app(\App\Services\Assistant\Tools\SearchCavesTool::class);
        $result = $tool->handle(['region' => 'Forest of Dean'], $user);

        $this->assertNotEmpty($result['cave_systems']);
        $this->assertStringContainsString('Otter', $result['cave_systems'][0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tool_fuzzy_search_matches_cave_entrance_names(): void
    {
        $user = User::factory()->create();
        $system = CaveSystem::factory()->create(['name' => 'Test System']);
        $system->tags()->attach($this->curatedTag->id);
        Cave::factory()->create(['cave_system_id' => $system->id, 'name' => 'Main Entrance']);
        Cave::factory()->create(['cave_system_id' => $system->id, 'name' => 'Back Entrance']);

        $tool = app(\App\Services\Assistant\Tools\SearchCavesTool::class);
        $result = $tool->handle(['name' => 'Main'], $user);

        $this->assertNotEmpty($result['cave_systems']);
    }
}
