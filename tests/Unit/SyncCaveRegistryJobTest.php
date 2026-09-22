<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\SyncCaveRegistryJob;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncCaveRegistryJobTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_the_command_output_in_the_failure_message(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('sync:fod-caves')
            ->andReturn(1);

        Artisan::shouldReceive('output')
            ->once()
            ->andReturn("Fetching fod cave placemarks...\nFailed to download KML from: http://example.test/kml (status 500)\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Cave registry sync 'fod' failed with exit code 1."
            .' Output: Fetching fod cave placemarks... | Failed to download KML from: http://example.test/kml (status 500)'
        );

        (new SyncCaveRegistryJob('fod'))->handle();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_falls_back_to_the_bare_message_when_the_command_produced_no_output(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(1);
        Artisan::shouldReceive('output')->once()->andReturn("  \n ");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cave registry sync 'fod' failed with exit code 1.");

        (new SyncCaveRegistryJob('fod'))->handle();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_truncates_very_long_output_and_keeps_the_tail(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(1);
        Artisan::shouldReceive('output')
            ->once()
            ->andReturn(str_repeat('x', 3000).'THE-REAL-ERROR');

        try {
            (new SyncCaveRegistryJob('fod'))->handle();
            $this->fail('Expected a RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('THE-REAL-ERROR', $e->getMessage());
            $this->assertStringContainsString('Output: ...', $e->getMessage());
            $this->assertLessThan(2200, mb_strlen($e->getMessage()));
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_throw_on_a_successful_sync(): void
    {
        Artisan::shouldReceive('call')->once()->with('sync:fod-caves')->andReturn(0);
        Artisan::shouldNotReceive('output');

        (new SyncCaveRegistryJob('fod'))->handle();

        $this->assertTrue(true);
    }
}
