<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServerTimingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function api_responses_report_connect_db_and_app_time(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $header = $this->getJson(route('users.me'))->assertOk()->headers->get('Server-Timing');

        $this->assertMatchesRegularExpression('/^connect;dur=[\d.]+, db;dur=[\d.]+;desc="\d+ queries", app;dur=[\d.]+$/', $header);
    }

    #[Test]
    public function repeated_requests_in_one_process_do_not_pile_up_query_listeners(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->getJson(route('users.me'))->assertOk();
        $listeners = \count(Event::getListeners(QueryExecuted::class));

        $header = $this->getJson(route('users.me'))->assertOk()->headers->get('Server-Timing');

        $this->assertCount($listeners, Event::getListeners(QueryExecuted::class));
        $this->assertDoesNotMatchRegularExpression('/desc="0 queries"/', $header);
    }
}
