<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessImageCloudJob;
use App\Models\CaveMedia;
use App\Models\RouteMedia;
use App\Models\TripMedia;
use Illuminate\Console\Command;

class ReprocessMediaImages extends Command
{
    /**
     * @var string
     */
    protected $signature = 'media:reprocess
        {--model=all : Which media to reprocess: all, cave, trip or route}
        {--limit= : Maximum number of records to dispatch (for testing)}
        {--dry-run : List what would be dispatched without queueing jobs}';

    /**
     * @var string
     */
    protected $description = 'Regenerate responsive WebP variants for existing media, preserving the original source file.';

    /** Video extensions are handled by the transcoder, not the image processor. */
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'mkv', 'm4v', 'webm'];

    /**
     * @var array<string, array{class: class-string, attribute: string}>
     */
    private const MODELS = [
        'cave' => ['class' => CaveMedia::class, 'attribute' => 'filename'],
        'trip' => ['class' => TripMedia::class, 'attribute' => 'filename'],
        'route' => ['class' => RouteMedia::class, 'attribute' => 'path'],
    ];

    public function handle(): int
    {
        if (!config('services.gcp.media_processing_enabled', true)) {
            $this->warn('GCP media processing is disabled (GCP_MEDIA_PROCESSING_ENABLED=false). Nothing to do.');

            return self::SUCCESS;
        }

        $which = (string) $this->option('model');
        $models = $which === 'all' ? self::MODELS : array_intersect_key(self::MODELS, [$which => true]);

        if (empty($models)) {
            $this->error("Unknown --model '{$which}'. Use: all, cave, trip or route.");

            return self::FAILURE;
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        $dispatched = 0;
        $skipped = 0;

        foreach ($models as $key => $config) {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $class */
            $class = $config['class'];
            $attribute = $config['attribute'];

            $this->info("Processing {$key} media...");

            $class::query()
                ->whereNotNull($attribute)
                ->orderBy('id')
                ->chunkById(200, function ($records) use ($class, $attribute, $limit, $dryRun, &$dispatched, &$skipped) {
                    foreach ($records as $record) {
                        if ($limit !== null && $dispatched >= $limit) {
                            return false;
                        }

                        $current = (string) $record->{$attribute};
                        if ($current === '' || str_starts_with($current, 'http')) {
                            ++$skipped;

                            continue;
                        }

                        $extension = strtolower(pathinfo($current, PATHINFO_EXTENSION));
                        if (in_array($extension, self::VIDEO_EXTENSIONS, true)) {
                            ++$skipped;

                            continue;
                        }

                        // Prefer a preserved original; otherwise the current
                        // (possibly already-desktop) file is the best source.
                        $source = $record->original_filename ?: $current;
                        $namingBase = $this->stripVariantSuffix($source);

                        if ($dryRun) {
                            $this->line("  [{$record->id}] {$source}  ->  {$namingBase}_{desktop,tablet,mobile}.webp");
                        } else {
                            ProcessImageCloudJob::dispatch($source, $class, $record->id, $namingBase);
                        }

                        ++$dispatched;
                    }

                    return true;
                });
        }

        $verb = $dryRun ? 'Would dispatch' : 'Dispatched';
        $this->info("{$verb} {$dispatched} job(s). Skipped {$skipped} (video/external/empty).");

        return self::SUCCESS;
    }

    /**
     * Remove a trailing variant suffix (_desktop/_tablet/_mobile) so re-processing
     * an already-processed image names its variants from the clean base rather
     * than producing e.g. `foo_desktop_desktop.webp`.
     */
    private function stripVariantSuffix(string $path): string
    {
        return (string) preg_replace('/_(?:desktop|tablet|mobile)(\.[A-Za-z0-9]+)$/', '$1', $path);
    }
}
