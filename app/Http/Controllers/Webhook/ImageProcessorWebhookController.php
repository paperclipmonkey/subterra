<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageProcessorWebhookController extends Controller
{
    private const MEDIA_MODEL_MAP = [
        'cave_media' => ['class' => \App\Models\CaveMedia::class, 'attribute' => 'filename'],
        'trip_media' => ['class' => \App\Models\TripMedia::class, 'attribute' => 'filename'],
        'route_media' => ['class' => \App\Models\RouteMedia::class, 'attribute' => 'path'],
    ];

    /**
     * Handle Pub/Sub Push Notification from GCS Image Processor.
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $messageData = $request->input('message.data');

        if (!$messageData) {
            Log::warning('ImageProcessorWebhook: received request without Pub/Sub message data');

            return response()->json(['status' => 'ignored'], 200);
        }

        $notification = json_decode(base64_decode($messageData), true);

        if (!$notification || ($notification['status'] ?? '') !== 'succeeded') {
            return response()->json(['status' => 'ignored'], 200);
        }

        $mediaModelLabel = $notification['mediaModel'] ?? '';
        $mediaId = (int) ($notification['mediaId'] ?? 0);
        $variants = $notification['variants'] ?? [];
        $sourcePath = $notification['sourcePath'] ?? '';
        $originalPath = $notification['originalPath'] ?? '';

        $modelConfig = self::MEDIA_MODEL_MAP[$mediaModelLabel] ?? null;
        if (!$modelConfig || !$mediaId) {
            Log::error('ImageProcessorWebhook: missing or invalid job labels', ['labels' => $notification]);

            return response()->json(['status' => 'ignored'], 200);
        }

        $mediaClass = $modelConfig['class'];
        $filenameAttribute = $modelConfig['attribute'];

        $media = $mediaClass::find($mediaId);
        if (!$media) {
            Log::warning("ImageProcessorWebhook: {$mediaClass} #{$mediaId} not found, skipping.");

            return response()->json(['status' => 'ignored'], 200);
        }

        if (empty($variants)) {
            Log::error('ImageProcessorWebhook: no variants in notification', ['media_id' => $mediaId]);

            return response()->json(['status' => 'ignored'], 200);
        }

        try {
            $currentPath = $originalPath ?: $media->{$filenameAttribute};
            $directory = dirname($currentPath);
            $baseName = pathinfo($currentPath, PATHINFO_FILENAME);

            $storedPaths = [];

            // Stream each variant from GCS staging to S3
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

            // Update the media record to point at the desktop-size WebP
            $primaryPath = $storedPaths['desktop'] ?? reset($storedPaths);
            $media->update([$filenameAttribute => $primaryPath]);

            // Clean up GCS staging files
            foreach ($variants as $variant) {
                Storage::disk('gcs_staging')->delete($variant['path']);
            }
            if ($sourcePath) {
                Storage::disk('gcs_staging')->delete($sourcePath);
                $inputDir = dirname($sourcePath).'/';
                $this->deleteGcsDirectory($inputDir);
            }

            // Delete original file from S3
            if ($currentPath && $currentPath !== $primaryPath) {
                Storage::disk('s3_clone')->delete($currentPath);
            }

            Log::info('ImageProcessorWebhook: image variants stored async', [
                'media_model' => $mediaClass,
                'media_id' => $mediaId,
                'variants' => array_keys($storedPaths),
                'primary_path' => $primaryPath,
            ]);
        } catch (\Throwable $e) {
            Log::error('ImageProcessorWebhook: failed to move variants', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error'], 500);
        }

        return response()->json(['status' => 'ok']);
    }

    private function deleteGcsDirectory(string $directory): void
    {
        $files = Storage::disk('gcs_staging')->allFiles($directory);
        foreach ($files as $file) {
            Storage::disk('gcs_staging')->delete($file);
        }
    }
}
