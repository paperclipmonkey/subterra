<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessVideoJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 300; // 5 minutes max execution time

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $originalPath,
        public readonly string $directory,
        public readonly string $filename
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Generate poster (webp) at 0.5s or 0s
            $posterFilename = $this->filename.'.webp';
            \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk('media')
                ->open($this->originalPath)
                ->getFrameFromSeconds(0.5)
                ->export()
                ->toDisk('media')
                ->save($this->directory.'/'.$posterFilename);

            // Export preview (webm), muted, scaled down to 480p width
            $format = new \FFMpeg\Format\Video\WebM();
            $format->setAdditionalParameters(['-an']); // Mute audio

            \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk('media')
                ->open($this->originalPath)
                ->resize(854, 480, 'width')
                ->export()
                ->toDisk('media')
                ->inFormat($format)
                ->save($this->directory.'/'.$this->filename.'.webm');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Background video processing failed: '.$e->getMessage());
        }
    }
}
