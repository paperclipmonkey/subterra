<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Artisan;

class SyncCaveRegistryJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum number of characters of command output to carry into the
     * exception message, so a failure stays diagnosable without flooding logs.
     */
    private const OUTPUT_EXCERPT_LIMIT = 2000;

    public $timeout = 600;

    public $tries = 1;

    public function __construct(public readonly string $registry)
    {
    }

    /**
     * Prevent two syncs of the same registry from running concurrently.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('sync-cave-registry:'.$this->registry))->expireAfter($this->timeout),
        ];
    }

    public function handle(): void
    {
        $exitCode = Artisan::call('sync:'.$this->registry.'-caves');

        if ($exitCode !== 0) {
            $message = "Cave registry sync '{$this->registry}' failed with exit code {$exitCode}.";

            if ($excerpt = $this->outputExcerpt(Artisan::output())) {
                $message .= ' Output: '.$excerpt;
            }

            throw new \RuntimeException($message);
        }
    }

    /**
     * Take the tail of the command output, which is where the error that
     * caused the non-zero exit is reported.
     */
    private function outputExcerpt(string $output): string
    {
        $output = trim($output);

        if ($output === '') {
            return '';
        }

        $output = (string) preg_replace('/\s*\R\s*/', ' | ', $output);

        if (mb_strlen($output) > self::OUTPUT_EXCERPT_LIMIT) {
            $output = '...'.mb_substr($output, -self::OUTPUT_EXCERPT_LIMIT);
        }

        return $output;
    }
}
