<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\Collection;
use App\Models\User;
use App\Services\Assistant\Tools\Admin\CreateCollectionTool;
use App\Services\Assistant\Tools\Admin\DeleteCollectionTool;
use App\Services\Assistant\Tools\Admin\UpdateCollectionTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class AssistantCollectionStewardTest extends TestCase
{
    use RefreshDatabase;

    private User $steward;

    protected function setUp(): void
    {
        parent::setUp();
        $this->steward = User::factory()->dataAdmin()->create();
    }

    // -------------------------------------------------------------------------
    // CreateCollectionTool
    // -------------------------------------------------------------------------

    #[Test]
    public function create_collection_creates_a_live_collection_owned_by_the_steward(): void
    {
        $result = app(CreateCollectionTool::class)->handle([
            'name' => 'Mendip Classics',
            'description' => 'The must-do Mendip trips.',
        ], $this->steward);

        $this->assertTrue($result['success']);
        $this->assertSame('mendip-classics', $result['slug']);

        $this->assertDatabaseHas('collections', [
            'id' => $result['collection_id'],
            'name' => 'Mendip Classics',
            'slug' => 'mendip-classics',
            'description' => 'The must-do Mendip trips.',
            'user_id' => $this->steward->id,
        ]);
    }

    #[Test]
    public function create_collection_attaches_caves_by_slug_with_order_and_notes(): void
    {
        $first = Cave::factory()->create(['slug' => 'swildons-hole']);
        $second = Cave::factory()->create(['slug' => 'gb-cave']);

        $result = app(CreateCollectionTool::class)->handle([
            'name' => 'Two Caver',
            'caves' => [
                ['slug' => 'swildons-hole', 'note' => 'Start here'],
                ['slug' => 'gb-cave'],
            ],
        ], $this->steward);

        $this->assertSame(2, $result['cave_count']);

        $this->assertDatabaseHas('cave_collection', [
            'collection_id' => $result['collection_id'],
            'cave_id' => $first->id,
            'description' => 'Start here',
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('cave_collection', [
            'collection_id' => $result['collection_id'],
            'cave_id' => $second->id,
            'sort_order' => 1,
        ]);
    }

    #[Test]
    public function create_collection_skips_and_reports_unknown_cave_slugs(): void
    {
        $known = Cave::factory()->create(['slug' => 'real-cave']);

        $result = app(CreateCollectionTool::class)->handle([
            'name' => 'Partly Real',
            'caves' => [
                ['slug' => 'real-cave'],
                ['slug' => 'ghost-cave'],
            ],
        ], $this->steward);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['cave_count']);
        $this->assertSame(['ghost-cave'], $result['unknown_cave_slugs']);
        $this->assertDatabaseHas('cave_collection', [
            'collection_id' => $result['collection_id'],
            'cave_id' => $known->id,
        ]);
    }

    #[Test]
    public function create_collection_rejects_a_blank_name(): void
    {
        $result = app(CreateCollectionTool::class)->handle(['name' => '   '], $this->steward);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, Collection::count());
    }

    #[Test]
    public function create_collection_rejects_a_duplicate_slug(): void
    {
        Collection::factory()->create(['name' => 'Mendip Classics', 'slug' => 'mendip-classics']);

        $result = app(CreateCollectionTool::class)->handle([
            'name' => 'Mendip Classics',
        ], $this->steward);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(1, Collection::count());
    }

    // -------------------------------------------------------------------------
    // UpdateCollectionTool
    // -------------------------------------------------------------------------

    #[Test]
    public function update_collection_renames_without_changing_the_slug(): void
    {
        $collection = Collection::factory()->create([
            'name' => 'Old Name',
            'slug' => 'old-name',
        ]);

        $result = app(UpdateCollectionTool::class)->handle([
            'collection_id' => $collection->id,
            'name' => 'New Name',
            'description' => 'Refreshed.',
        ], $this->steward);

        $this->assertTrue($result['success']);
        $this->assertSame('old-name', $result['slug']);
        $this->assertDatabaseHas('collections', [
            'id' => $collection->id,
            'name' => 'New Name',
            'slug' => 'old-name',
            'description' => 'Refreshed.',
        ]);
    }

    #[Test]
    public function update_collection_can_be_resolved_by_slug(): void
    {
        $collection = Collection::factory()->create(['slug' => 'find-me']);

        $result = app(UpdateCollectionTool::class)->handle([
            'slug' => 'find-me',
            'name' => 'Found It',
        ], $this->steward);

        $this->assertTrue($result['success']);
        $this->assertSame('Found It', $collection->fresh()->name);
    }

    #[Test]
    public function update_collection_replaces_the_full_cave_list_when_caves_given(): void
    {
        $collection = Collection::factory()->create();
        $original = Cave::factory()->create(['slug' => 'original-cave']);
        $replacement = Cave::factory()->create(['slug' => 'replacement-cave']);
        $collection->caves()->attach($original, ['sort_order' => 0]);

        $result = app(UpdateCollectionTool::class)->handle([
            'collection_id' => $collection->id,
            'caves' => [['slug' => 'replacement-cave']],
        ], $this->steward);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['cave_count']);
        $this->assertDatabaseMissing('cave_collection', [
            'collection_id' => $collection->id,
            'cave_id' => $original->id,
        ]);
        $this->assertDatabaseHas('cave_collection', [
            'collection_id' => $collection->id,
            'cave_id' => $replacement->id,
        ]);
    }

    #[Test]
    public function update_collection_leaves_caves_untouched_when_caves_omitted(): void
    {
        $collection = Collection::factory()->create();
        $cave = Cave::factory()->create(['slug' => 'kept-cave']);
        $collection->caves()->attach($cave, ['sort_order' => 0]);

        app(UpdateCollectionTool::class)->handle([
            'collection_id' => $collection->id,
            'name' => 'Just A Rename',
        ], $this->steward);

        $this->assertDatabaseHas('cave_collection', [
            'collection_id' => $collection->id,
            'cave_id' => $cave->id,
        ]);
    }

    #[Test]
    public function update_collection_empty_caves_array_removes_all_caves(): void
    {
        $collection = Collection::factory()->create();
        $cave = Cave::factory()->create(['slug' => 'doomed-cave']);
        $collection->caves()->attach($cave, ['sort_order' => 0]);

        $result = app(UpdateCollectionTool::class)->handle([
            'collection_id' => $collection->id,
            'caves' => [],
        ], $this->steward);

        $this->assertSame(0, $result['cave_count']);
        $this->assertDatabaseMissing('cave_collection', [
            'collection_id' => $collection->id,
            'cave_id' => $cave->id,
        ]);
    }

    #[Test]
    public function update_collection_returns_an_error_when_not_found(): void
    {
        $result = app(UpdateCollectionTool::class)->handle([
            'slug' => 'does-not-exist',
            'name' => 'Nope',
        ], $this->steward);

        $this->assertArrayHasKey('error', $result);
    }

    // -------------------------------------------------------------------------
    // DeleteCollectionTool
    // -------------------------------------------------------------------------

    #[Test]
    public function delete_collection_removes_the_collection_but_keeps_the_caves(): void
    {
        $collection = Collection::factory()->create(['name' => 'Doomed', 'slug' => 'doomed']);
        $cave = Cave::factory()->create(['slug' => 'survivor-cave']);
        $collection->caves()->attach($cave, ['sort_order' => 0]);

        $result = app(DeleteCollectionTool::class)->handle([
            'collection_id' => $collection->id,
        ], $this->steward);

        $this->assertTrue($result['success']);
        $this->assertSame('Doomed', $result['deleted_collection']);
        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
        $this->assertDatabaseMissing('cave_collection', ['collection_id' => $collection->id]);
        // The cave itself is untouched
        $this->assertDatabaseHas('caves', ['id' => $cave->id]);
    }

    #[Test]
    public function delete_collection_can_be_resolved_by_slug(): void
    {
        $collection = Collection::factory()->create(['slug' => 'kill-by-slug']);

        $result = app(DeleteCollectionTool::class)->handle([
            'slug' => 'kill-by-slug',
        ], $this->steward);

        $this->assertTrue($result['success']);
        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
    }

    #[Test]
    public function delete_collection_returns_an_error_when_not_found(): void
    {
        $result = app(DeleteCollectionTool::class)->handle([
            'slug' => 'never-existed',
        ], $this->steward);

        $this->assertArrayHasKey('error', $result);
    }

    // -------------------------------------------------------------------------
    // End-to-end: the chat stream emits a collections_changed confirmation event
    // -------------------------------------------------------------------------

    #[Test]
    public function data_mode_chat_emits_a_collections_changed_event_after_creating_one(): void
    {
        config(['assistant.openrouter.api_key' => 'test-key']);

        // First model turn calls create_collection; second turn writes the reply.
        Http::fake([
            'openrouter.ai/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => '',
                            'tool_calls' => [[
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'create_collection',
                                    'arguments' => json_encode(['name' => 'Streamed Collection']),
                                ],
                            ]],
                        ],
                        'finish_reason' => 'tool_calls',
                    ]],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
                ])
                ->push([
                    'choices' => [[
                        'message' => ['role' => 'assistant', 'content' => 'Done — created the collection.'],
                        'finish_reason' => 'stop',
                    ]],
                    'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 4],
                ]),
        ]);

        // platform_admin satisfies both the PipAccess middleware and the data-mode gate
        $admin = User::factory()->admin()->pipAgreed()->create();

        $response = $this->actingAs($admin)->postJson('/api/assistant/chat', [
            'messages' => [['role' => 'user', 'content' => 'Create a collection called Streamed Collection']],
            'mode' => 'data',
        ]);

        $response->assertStatus(200);
        $body = $this->captureStream($response);

        // The collection was created live, and the stream carried a confirmation event
        $this->assertDatabaseHas('collections', [
            'name' => 'Streamed Collection',
            'user_id' => $admin->id,
        ]);
        $this->assertStringContainsString('collections_changed', $body);
        $this->assertStringContainsString('"action":"created"', $body);
        $this->assertStringContainsString('Streamed Collection', $body);
    }

    /** Capture the full SSE body emitted by a streamed chat response. */
    private function captureStream($response): string
    {
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);

        $startLevel = ob_get_level();
        ob_start();
        ob_start();
        ob_start();
        $response->baseResponse->sendContent();

        $captured = '';
        while (ob_get_level() > $startLevel) {
            $captured = ob_get_clean().$captured;
        }

        return $captured;
    }
}
