<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GcpMediaWebhookController extends Controller
{
    private const MEDIA_MODEL_MAP = [
        'cave_media' => ['class' => \App\Models\CaveMedia::class, 'attribute' => 'filename'],
        'trip_media' => ['class' => \App\Models\TripMedia::class, 'attribute' => 'filename'],
        'route_media' => ['class' => \App\Models\RouteMedia::class, 'attribute' => 'path'],
        'permit' => ['class' => \App\Models\Permit::class, 'attribute' => 'photo_path'],
    ];

    /**
     * Handle Pub/Sub Push Notification for all GCP media jobs.
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = $request->query('token');
        $secret = config('services.gcp.webhook_secret');

        if (empty($secret) || !hash_equals($secret, (string) $token)) {
            Log::warning('GcpMediaWebhook: unauthorized access attempted');

            return response()->json(['status' => 'unauthorized'], 401);
        }

        $messageData = $request->input('message.data');

        if (!$messageData) {
            Log::warning('GcpMediaWebhook: received request without Pub/Sub message data');

            return response()->json(['status' => 'ignored'], 200);
        }

        $notification = json_decode(base64_decode($messageData), true);

        if (!$notification) {
            return response()->json(['status' => 'ignored'], 200);
        }

        // Branch between Video (GCP Transcoder labels) and Image Processor sets
        if (isset($notification['labels'])) {
            return $this->handleVideo($notification);
        } else {
            return $this->handleImage($notification);
        }
    }

    /**
     * Handle Async Image Processor completes.
     */
    protected function handleImage(array $notification): \Illuminate\Http\JsonResponse
    {
        if (($notification['status'] ?? '') !== 'succeeded') {
            return response()->json(['status' => 'ignored'], 200);
        }

        $mediaModelLabel = $notification['mediaModel'] ?? '';
        $mediaId = (int) ($notification['mediaId'] ?? 0);
        $variants = $notification['variants'] ?? [];
        $sourcePath = $notification['sourcePath'] ?? '';
        $originalPath = $notification['originalPath'] ?? '';
        $namingBase = $notification['namingBase'] ?? '';

        return $this->processImageVariants($mediaModelLabel, $mediaId, $variants, $sourcePath, $originalPath, $namingBase);
    }

    /**
     * Handle GCP Transcoder video completion.
     */
    protected function handleVideo(array $notification): \Illuminate\Http\JsonResponse
    {
        if (($notification['state'] ?? '') !== 'SUCCEEDED') {
            return response()->json(['status' => 'ignored'], 200);
        }

        $labels = $notification['labels'] ?? [];
        $mediaModelLabel = $labels['media_model'] ?? '';
        $mediaId = (int) ($labels['media_id'] ?? 0);
        $outputDir = base64_decode($labels['output_dir'] ?? '');
        $inputPrefix = base64_decode($labels['input_prefix'] ?? '');

        $modelConfig = self::MEDIA_MODEL_MAP[$mediaModelLabel] ?? null;
        if (!$modelConfig || !$mediaId || !$outputDir) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $mediaClass = $modelConfig['class'];
        $filenameAttribute = $modelConfig['attribute'];

        $media = $mediaClass::find($mediaId);
        if (!$media) {
            return response()->json(['status' => 'ignored'], 200);
        }

        // Transcoder outputs with static single layout
        $gcsOutputPath = rtrim($outputDir, '/').'/'.'sd0000000000.mp4';
        $currentPath = $media->{$filenameAttribute};
        $s3DestPath = dirname($currentPath).'/'.pathinfo($currentPath, PATHINFO_FILENAME).'.mp4';

        try {
            Storage::disk('s3_clone')->writeStream(
                $s3DestPath,
                Storage::disk('gcs_staging')->readStream($gcsOutputPath)
            );

            $media->update([$filenameAttribute => $s3DestPath]);

            Log::info('GcpMediaWebhook: video transcoded & stored', ['media_id' => $mediaId]);

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            Log::error('GcpMediaWebhook: failed to move transcoded video', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Relocate image variants back to primary storage.
     *
     * The source image is preserved as `original_filename` (never deleted) so
     * images can be re-processed in future without quality loss. Variants are
     * named from $namingBase, which is the source path with any variant suffix
     * stripped, so re-processing an already-processed image doesn't produce a
     * double suffix (e.g. `foo_desktop_desktop.webp`).
     */
    protected function processImageVariants(string $mediaModelLabel, int $mediaId, array $variants, string $sourcePath, string $originalPath, string $namingBase = ''): \Illuminate\Http\JsonResponse
    {
        $modelConfig = self::MEDIA_MODEL_MAP[$mediaModelLabel] ?? null;
        if (!$modelConfig || !$mediaId || empty($variants)) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $mediaClass = $modelConfig['class'];
        $filenameAttribute = $modelConfig['attribute'];

        $media = $mediaClass::find($mediaId);
        if (!$media) {
            return response()->json(['status' => 'ignored'], 200);
        }

        try {
            // The file whose bytes were processed — preserved as the original.
            $processedSource = $originalPath ?: $media->{$filenameAttribute};
            // The base used to name the generated variants.
            $namingPath = $namingBase ?: $processedSource;
            $directory = dirname($namingPath);
            $baseName = pathinfo($namingPath, PATHINFO_FILENAME);

            $storedPaths = [];

            foreach ($variants as $variant) {
                $destPath = $directory.'/'.$baseName.'_'.$variant['name'].'.webp';
                $gcsStream = Storage::disk('gcs_staging')->readStream($variant['path']);

                if ($gcsStream) {
                    Storage::disk('s3_clone')->writeStream($destPath, $gcsStream);
                    if (is_resource($gcsStream)) {
                        fclose($gcsStream);
                    }
                    $storedPaths[$variant['name']] = $destPath;
                }
            }

            $primaryPath = $storedPaths['desktop'] ?? reset($storedPaths);

            $media->{$filenameAttribute} = $primaryPath;
            // Record the preserved source once. Never overwrite an existing
            // original, and never point it at a generated variant.
            if (empty($media->original_filename) && $processedSource && !in_array($processedSource, $storedPaths, true)) {
                $media->original_filename = $processedSource;
            }
            $media->save();

            Log::info('GcpMediaWebhook: image variants stored async', ['media_id' => $mediaId]);

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            Log::error('GcpMediaWebhook: failed to move image variants', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error'], 500);
        }
    }
}
