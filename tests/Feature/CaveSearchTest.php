<?php

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JsonSchemaValidator;
use Tests\TestCase;

class CaveSearchTest extends TestCase
{
    use RefreshDatabase;
    use JsonSchemaValidator;

    private Tag $curatedTag;

    private Tag $closedTag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TagSeeder::class);
        $this->curatedTag = Tag::where('tag', 'Curated')->where('category', 'curated')->firstOrFail();
        $this->closedTag = Tag::where('tag', 'Closed')->firstOrFail();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_all_caves_with_correct_schema()
    {
        $this->actingAs(User::factory()->create());
        Cave::factory()->count(3)->create();

        $response = $this->getJson('/api/caves/search');

        $response->assertOk();
        $this->assertResponseMatchesSchema($response, 'endpoints/caves-search');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/caves/search');

        $response->assertUnauthorized();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_marks_curated_caves_correctly()
    {
        $this->actingAs(User::factory()->create());

        $curated = Cave::factory()->create(['name' => 'Curated Cave']);
        $curated->tags()->attach($this->curatedTag->id);

        $nonCurated = Cave::factory()->create(['name' => 'Non Curated Cave']);

        $response = $this->getJson('/api/caves/search');

        $response->assertOk();

        $data = $response->json('data');
        $curatedItem = collect($data)->firstWhere('id', $curated->id);
        $nonCuratedItem = collect($data)->firstWhere('id', $nonCurated->id);

        $this->assertTrue($curatedItem['is_curated']);
        $this->assertFalse($nonCuratedItem['is_curated']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_marks_closed_caves_correctly()
    {
        $this->actingAs(User::factory()->create());

        $closed = Cave::factory()->create(['name' => 'Closed Cave']);
        $closed->tags()->attach($this->closedTag->id);

        $open = Cave::factory()->create(['name' => 'Open Cave']);

        $response = $this->getJson('/api/caves/search');

        $response->assertOk();

        $data = $response->json('data');
        $closedItem = collect($data)->firstWhere('id', $closed->id);
        $openItem = collect($data)->firstWhere('id', $open->id);

        $this->assertTrue($closedItem['is_closed']);
        $this->assertFalse($openItem['is_closed']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_orders_curated_caves_first()
    {
        $this->actingAs(User::factory()->create());

        $nonCurated = Cave::factory()->create(['name' => 'AAA Non Curated']);
        $curated = Cave::factory()->create(['name' => 'ZZZ Curated Cave']);
        $curated->tags()->attach($this->curatedTag->id);

        $response = $this->getJson('/api/caves/search');

        $response->assertOk();

        $data = $response->json('data');
        $ids = collect($data)->pluck('id')->all();

        $this->assertLessThan(
            array_search($nonCurated->id, $ids),
            array_search($curated->id, $ids),
            'Curated cave should appear before non-curated cave regardless of name'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_orders_alphabetically_within_curated_and_non_curated_groups()
    {
        $this->actingAs(User::factory()->create());

        $curatedB = Cave::factory()->create(['name' => 'B Curated']);
        $curatedA = Cave::factory()->create(['name' => 'A Curated']);
        $curatedB->tags()->attach($this->curatedTag->id);
        $curatedA->tags()->attach($this->curatedTag->id);

        $nonCuratedD = Cave::factory()->create(['name' => 'D Non Curated']);
        $nonCuratedC = Cave::factory()->create(['name' => 'C Non Curated']);

        $response = $this->getJson('/api/caves/search');

        $response->assertOk();

        $data = $response->json('data');
        $names = collect($data)->pluck('name')->all();

        $this->assertEquals(['A Curated', 'B Curated', 'C Non Curated', 'D Non Curated'], $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_cave_system_id()
    {
        $this->actingAs(User::factory()->create());

        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);

        $response = $this->getJson('/api/caves/search');

        $response->assertOk();

        $data = $response->json('data');
        $item = collect($data)->firstWhere('id', $cave->id);

        $this->assertEquals($system->id, $item['cave_system_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_cave_system_id_for_all_caves()
    {
        $this->actingAs(User::factory()->create());

        $systemA = CaveSystem::factory()->create();
        $systemB = CaveSystem::factory()->create();
        $caveA = Cave::factory()->create(['cave_system_id' => $systemA->id]);
        $caveB = Cave::factory()->create(['cave_system_id' => $systemB->id]);

        $response = $this->getJson('/api/caves/search');

        $response->assertOk();

        $data = $response->json('data');
        $itemA = collect($data)->firstWhere('id', $caveA->id);
        $itemB = collect($data)->firstWhere('id', $caveB->id);

        $this->assertEquals($systemA->id, $itemA['cave_system_id']);
        $this->assertEquals($systemB->id, $itemB['cave_system_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_only_required_fields()
    {
        $this->actingAs(User::factory()->create());

        Cave::factory()->create();

        $response = $this->getJson('/api/caves/search');

        $response->assertOk();

        $item = $response->json('data.0');

        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('location_name', $item);
        $this->assertArrayHasKey('location_country', $item);
        $this->assertArrayHasKey('cave_system_id', $item);
        $this->assertArrayHasKey('is_curated', $item);
        $this->assertArrayHasKey('is_closed', $item);

        // Should NOT contain heavy fields from the main endpoint
        $this->assertArrayNotHasKey('hero_image', $item);
        $this->assertArrayNotHasKey('tags', $item);
        $this->assertArrayNotHasKey('system', $item);
        $this->assertArrayNotHasKey('previously_done', $item);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_array_when_no_caves_exist()
    {
        $this->actingAs(User::factory()->create());

        $response = $this->getJson('/api/caves/search');

        $response->assertOk()
            ->assertJson(['data' => []]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_location_fields()
    {
        $this->actingAs(User::factory()->create());

        Cave::factory()->create([
            'location_name' => 'Yorkshire Dales',
            'location_country' => 'England',
        ]);

        $response = $this->getJson('/api/caves/search');

        $response->assertOk()
            ->assertJsonFragment([
                'location_name' => 'Yorkshire Dales',
                'location_country' => 'England',
            ]);
    }
}
