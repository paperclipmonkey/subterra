<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageProcessorWebhookController extends Controller
{
    /**
     * Media model label → [class, filename attribute] mapping.
     */
    private const MEDIA_MODEL_MAP = [
        'cave_media' => ['class' => \App\Models\CaveMedia::class, 'attribute' => 'filename'],
        'trip_media' => ['class' => \App\Models\TripMedia::class, 'attribute' => 'filename'],
        'route_media' => ['class' => \App\Models\RouteMedia::class, 'attribute' => 'path'],
    ];

    /**
     * Handle callback from the GCP Cloud Run image processor.
     *
     * The processor sends a JSON payload with:
     *   - status: "succeeded" or "failed"
     *   - mediaModel: snake_case model label
     *   - mediaId: primary key
     *   - variants: array of { name, path, width, height, size }
     *   - sourcePath: original GCS input path
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        // Verify callback bearer token
        $expectedSecret = config('services.gcp.webhook_secret');
        if ($expectedSecret) {
            $providedToken = $request->bearerToken();
            if (!$providedToken || !hash_equals($expectedSecret, $providedToken)) {
                Log::warning('ImageProcessorWebhook: unauthorized request', ['ip' => $request->ip()]);

                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }
        }

        $status = $request->input('status');
        $mediaModelLabel = $request->input('mediaModel');
        $mediaId = (int) $request->input('mediaId', 0);

        if ($status !== 'succeeded') {
            Log::warning('ImageProcessorWebhook: processing failed', [
                'media_model' => $mediaModelLabel,
                'media_id' => $mediaId,
                'error' => $request->input('error'),
            ]);

            return response()->json(['status' => 'ignored'], 200);
        }

        $modelConfig = self::MEDIA_MODEL_MAP[$mediaModelLabel] ?? null;
        if (!$modelConfig || !$mediaId) {
            Log::error('ImageProcessorWebhook: missing or invalid fields', [
                'media_model' => $mediaModelLabel,
                'media_id' => $mediaId,
            ]);

            return response()->json(['status' => 'ignored'], 200);
        }

        $mediaClass = $modelConfig['class'];
        $filenameAttribute = $modelConfig['attribute'];

        $media = $mediaClass::find($mediaId);
        if (!$media) {
            Log::warning("ImageProcessorWebhook: {$mediaClass} #{$mediaId} not found, skipping.");

            return response()->json(['status' => 'ignored'], 200);
        }

        $variants = $request->input('variants', []);
        $sourcePath = $request->input('sourcePath', '');

        if (empty($variants)) {
            Log::error('ImageProcessorWebhook: no variants in callback', ['media_id' => $mediaId]);

            return response()->json(['status' => 'ignored'], 200);
        }

        try {
            // Use the desktop variant as the primary image (largest)
            $desktopVariant = collect($variants)->firstWhere('name', 'desktop') ?? $variants[0];
            $currentPath = $media->{$filenameAttribute};
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
                // Try to remove the parent input directory
                $inputDir = dirname($sourcePath).'/';
                $this->deleteGcsDirectory($inputDir);
            }

            // Delete the original raw file from S3 (it's been replaced by WebP variants)
            if ($currentPath !== $primaryPath) {
                Storage::disk('s3_clone')->delete($currentPath);
            }

            Log::info('ImageProcessorWebhook: image variants stored', [
                'media_model' => $mediaClass,
                'media_id' => $mediaId,
                'variants' => array_keys($storedPaths),
                'primary_path' => $primaryPath,
            ]);
        } catch (\Throwable $e) {
            Log::error('ImageProcessorWebhook: failed to process callback', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Internal error'], 500);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Delete all objects within a GCS "directory" prefix.
     */
    private function deleteGcsDirectory(string $directory): void
    {
        $files = Storage::disk('gcs_staging')->allFiles($directory);
        foreach ($files as $file) {
            Storage::disk('gcs_staging')->delete($file);
        }
    }
}
