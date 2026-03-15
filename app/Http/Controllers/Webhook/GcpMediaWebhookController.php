<?php

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
    ];

    /**
     * Handle Pub/Sub Push Notification for all GCP media jobs.
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = $request->query('token');
        $secret = config('services.gcp.webhook_secret');

        if ($secret && $token !== $secret) {
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

        return $this->processImageVariants($mediaModelLabel, $mediaId, $variants, $sourcePath, $originalPath);
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
     */
    protected function processImageVariants(string $mediaModelLabel, int $mediaId, array $variants, string $sourcePath, string $originalPath): \Illuminate\Http\JsonResponse
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
            $currentPath = $originalPath ?: $media->{$filenameAttribute};
            $directory = dirname($currentPath);
            $baseName = pathinfo($currentPath, PATHINFO_FILENAME);

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
            $media->update([$filenameAttribute => $primaryPath]);

            if ($currentPath && $currentPath !== $primaryPath) {
                Storage::disk('s3_clone')->delete($currentPath);
            }

            Log::info('GcpMediaWebhook: image variants stored async', ['media_id' => $mediaId]);

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            Log::error('GcpMediaWebhook: failed to move image variants', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error'], 500);
        }
    }
}
