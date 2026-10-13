<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\TripImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssistantEvalCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant.openrouter.api_key' => 'test-key']);
        Http::fake(['openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Done.'], 'finish_reason' => 'stop']],
        ])]);
        $this->dir = sys_get_temp_dir().'/assistant-eval-'.uniqid();
        File::makeDirectory($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    #[Test]
    public function importer_entries_stage_their_logbook_and_run_in_import_mode(): void
    {
        $user = User::factory()->pipAccess()->create();
        File::put($this->dir.'/dataset.json', json_encode([[
            'id' => 'imp',
            'category' => 'import',
            'mode' => 'default',
            'logbook' => "Date,Cave\n14/06/2024,Somewhere",
            'history' => [['role' => 'user', 'content' => 'Earlier'], ['role' => 'assistant', 'content' => 'Reply']],
            'prompt' => 'Help me import',
            'checks' => [],
        ]]));

        $this->artisan('assistant:eval', [
            '--user' => $user->id,
            '--dataset' => $this->dir.'/dataset.json',
            '--output' => $this->dir.'/report.md',
        ])->assertSuccessful();

        $this->assertSame(1, TripImport::where('user_id', $user->id)->first()->rows()->count());
        [$request] = Http::recorded()->first();
        $tools = array_map(fn ($t) => $t['function']['name'], $request->data()['tools']);
        $this->assertContains('get_import_status', $tools);
        // System prompt + history + prompt
        $this->assertCount(4, $request->data()['messages']);
        $this->assertFileExists($this->dir.'/report.md');
    }

    #[Test]
    public function the_mode_option_defaults_to_the_planner(): void
    {
        $user = User::factory()->admin()->create();
        File::put($this->dir.'/dataset.json', json_encode([['id' => 'p', 'category' => 'plan', 'prompt' => 'Hi', 'checks' => []]]));

        $this->artisan('assistant:eval', [
            '--user' => $user->id,
            '--dataset' => $this->dir.'/dataset.json',
            '--output' => $this->dir.'/report.md',
        ])->assertSuccessful();

        [$request] = Http::recorded()->first();
        $tools = array_map(fn ($t) => $t['function']['name'], $request->data()['tools']);
        $this->assertContains('search_caves', $tools);
    }
}
