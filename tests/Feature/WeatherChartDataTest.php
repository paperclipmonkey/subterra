<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\AssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeatherChartDataTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/assistant/chat';

    private function validPayload(): array
    {
        return [
            'messages' => [
                ['role' => 'user', 'content' => 'Check weather conditions'],
            ],
        ];
    }

    private function parseSseContent(string $body): array
    {
        $events = [];
        foreach (explode("\n\n", $body) as $chunk) {
            $chunk = trim($chunk);
            if (str_starts_with($chunk, 'data: ')) {
                $json = substr($chunk, 6);
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $events[] = $decoded;
                }
            }
        }

        return $events;
    }

    private function captureStream(\Illuminate\Testing\TestResponse $response): string
    {
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response->baseResponse);

        $startLevel = ob_get_level();
        ob_start();
        ob_start();
        ob_start();

        $response->baseResponse->sendContent();

        while (ob_get_level() > $startLevel + 1) {
            ob_end_clean();
        }

        return ob_get_clean() ?: '';
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function weather_data_includes_cave_slug_for_linking(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $weatherData = [
            'cave_id' => 42,
            'cave_slug' => 'swildons-hole',
            'cave_name' => 'Swildon\'s Hole',
            'antecedent_rain_7d_mm' => 15.3,
            'rain_gauges' => [],
            'river_gauges' => [],
        ];

        $this->mock(AssistantService::class, function ($mock) use ($weatherData) {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturnUsing(function ($messages, $user, $onEvent) use ($weatherData) {
                    $onEvent('weather_charts', $weatherData);

                    return 'Weather data retrieved.';
                });
        });

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $body = $this->captureStream($response);
        $events = $this->parseSseContent($body);
        $weatherEvent = collect($events)->firstWhere('type', 'weather_charts');

        $this->assertNotNull($weatherEvent);
        $this->assertSame('swildons-hole', $weatherEvent['data']['cave_slug']);
        $this->assertSame(42, $weatherEvent['data']['cave_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function weather_data_includes_antecedent_rainfall(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $weatherData = [
            'cave_id' => 1,
            'cave_slug' => 'test-cave',
            'cave_name' => 'Test Cave',
            'antecedent_rain_7d_mm' => 25.5,
            'rain_gauges' => [],
            'river_gauges' => [],
        ];

        $this->mock(AssistantService::class, function ($mock) use ($weatherData) {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturnUsing(function ($messages, $user, $onEvent) use ($weatherData) {
                    $onEvent('weather_charts', $weatherData);

                    return 'Done';
                });
        });

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $body = $this->captureStream($response);
        $events = $this->parseSseContent($body);
        $weatherEvent = collect($events)->firstWhere('type', 'weather_charts');

        $this->assertSame(25.5, $weatherEvent['data']['antecedent_rain_7d_mm']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function weather_data_includes_rain_gauge_readings(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $weatherData = [
            'cave_id' => 1,
            'cave_slug' => 'test-cave',
            'cave_name' => 'Test Cave',
            'antecedent_rain_7d_mm' => 10.0,
            'rain_gauges' => [
                ['name' => 'Station A', 'readings_24h_mm' => 5.2],
                ['name' => 'Station B', 'readings_24h_mm' => 8.1],
            ],
            'river_gauges' => [],
        ];

        $this->mock(AssistantService::class, function ($mock) use ($weatherData) {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturnUsing(function ($messages, $user, $onEvent) use ($weatherData) {
                    $onEvent('weather_charts', $weatherData);

                    return 'Done';
                });
        });

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $body = $this->captureStream($response);
        $events = $this->parseSseContent($body);
        $weatherEvent = collect($events)->firstWhere('type', 'weather_charts');

        $this->assertCount(2, $weatherEvent['data']['rain_gauges']);
        $this->assertSame('Station A', $weatherEvent['data']['rain_gauges'][0]['name']);
        $this->assertSame(5.2, $weatherEvent['data']['rain_gauges'][0]['readings_24h_mm']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function weather_data_includes_river_gauge_with_trend(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $weatherData = [
            'cave_id' => 1,
            'cave_slug' => 'test-cave',
            'cave_name' => 'Test Cave',
            'antecedent_rain_7d_mm' => null,
            'rain_gauges' => [],
            'river_gauges' => [
                [
                    'name' => 'Wookey Gauge',
                    'state' => 'Normal',
                    'trend' => 'falling',
                    'latest_value' => '0.45 m',
                    'latest_time' => '2026-05-16T12:00:00Z',
                ],
            ],
        ];

        $this->mock(AssistantService::class, function ($mock) use ($weatherData) {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturnUsing(function ($messages, $user, $onEvent) use ($weatherData) {
                    $onEvent('weather_charts', $weatherData);

                    return 'Done';
                });
        });

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $body = $this->captureStream($response);
        $events = $this->parseSseContent($body);
        $weatherEvent = collect($events)->firstWhere('type', 'weather_charts');

        $this->assertCount(1, $weatherEvent['data']['river_gauges']);
        $gauge = $weatherEvent['data']['river_gauges'][0];
        $this->assertSame('Wookey Gauge', $gauge['name']);
        $this->assertSame('Normal', $gauge['state']);
        $this->assertSame('falling', $gauge['trend']);
        $this->assertSame('0.45 m', $gauge['latest_value']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function weather_data_includes_daily_forecast(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $weatherData = [
            'cave_id' => 1,
            'cave_slug' => 'test-cave',
            'cave_name' => 'Test Cave',
            'antecedent_rain_7d_mm' => 10.0,
            'daily_forecast' => [
                ['date' => '2026-05-17', 'precip_mm' => 2.5, 'precip_prob' => 40],
                ['date' => '2026-05-18', 'precip_mm' => 5.1, 'precip_prob' => 80],
                ['date' => '2026-05-19', 'precip_mm' => 0.0, 'precip_prob' => 10],
            ],
            'rain_gauges' => [],
            'river_gauges' => [],
        ];

        $this->mock(AssistantService::class, function ($mock) use ($weatherData) {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturnUsing(function ($messages, $user, $onEvent) use ($weatherData) {
                    $onEvent('weather_charts', $weatherData);

                    return 'Done';
                });
        });

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $body = $this->captureStream($response);
        $events = $this->parseSseContent($body);
        $weatherEvent = collect($events)->firstWhere('type', 'weather_charts');

        $this->assertCount(3, $weatherEvent['data']['daily_forecast']);
        $this->assertSame('2026-05-17', $weatherEvent['data']['daily_forecast'][0]['date']);
        $this->assertSame(2.5, $weatherEvent['data']['daily_forecast'][0]['precip_mm']);
    }
}
