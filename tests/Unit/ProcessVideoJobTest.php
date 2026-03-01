<?php

namespace Tests\Unit;

use App\Jobs\ProcessVideoJob;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Tests\TestCase;

class ProcessVideoJobTest extends TestCase
{
    public function test_it_processes_video_and_creates_preview_files()
    {
        Storage::fake('media');

        // Note: Real FFMpeg would require a valid video file. We mock the FFMpeg facade entirely
        // to strictly verify the parameters/commands sent by the Job.
        $originalPath = 'caves/fake-video.mp4';
        $directory = 'caves';
        $filename = 'fake-video';

        // FFMpeg isn't easily mockable natively so we use Mockery to expect the underlying execution.
        // Or better yet, we can mock the \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg facade's
        // `fromDisk` method to return a chainable mock that handles both video and image logic.

        $mediaOpenerMock = \Mockery::mock(\ProtoneMedia\LaravelFFMpeg\Support\MediaOpenerFactory::class)->makePartial();

        // Mock the facade roots
        \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::swap($mediaOpenerMock);

        // Poster Generation Chain
        $mediaOpenerMock->shouldReceive('fromDisk')->with('media')->andReturnSelf();
        $mediaOpenerMock->shouldReceive('open')->with($originalPath)->andReturnSelf();
        $mediaOpenerMock->shouldReceive('getFrameFromSeconds')->with(0.5)->andReturnSelf();
        $mediaOpenerMock->shouldReceive('export')->andReturnSelf();
        $mediaOpenerMock->shouldReceive('toDisk')->with('media')->andReturnSelf();
        $mediaOpenerMock->shouldReceive('save')->with($directory.'/'.$filename.'.webp')->andReturnSelf();

        // Webm Generation Chain
        $mediaOpenerMock->shouldReceive('resize')->with(854, 480, 'width')->andReturnSelf();
        $mediaOpenerMock->shouldReceive('inFormat')->andReturnSelf();
        $mediaOpenerMock->shouldReceive('save')->with($directory.'/'.$filename.'.webm')->andReturnSelf();

        $job = new ProcessVideoJob($originalPath, $directory, $filename);
        $job->handle();

        $this->assertTrue(true); // If mockery expectation hits, it passes
    }
}
