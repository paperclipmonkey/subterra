<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
