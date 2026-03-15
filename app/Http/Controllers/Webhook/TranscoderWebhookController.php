<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TranscoderWebhookController extends Controller
{
    /**
     * Media model label → [class, filename attribute] mapping.
     * RouteMedia uses 'path' instead of 'filename'.
     */
    private const MEDIA_MODEL_MAP = [
        'cave_media' => ['class' => \App\Models\CaveMedia::class, 'attribute' => 'filename'],
        'trip_media' => ['class' => \App\Models\TripMedia::class, 'attribute' => 'filename'],
        'route_media' => ['class' => \App\Models\RouteMedia::class, 'attribute' => 'path'],
    ];

    /**
     * Handle a Pub/Sub push notification from GCP when a transcoding job completes.
     *
     * GCP pushes a JSON payload with a base64-encoded `message.data` field containing
     * the job notification. When the job state is SUCCEEDED we move the transcoded MP4
     * from the GCS staging bucket back to the primary S3-compatible storage and update
     * the corresponding media record.
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        // Verify webhook token (Supports Bearer Auth or ?token= query parameter)
        $expectedSecret = config('services.gcp.webhook_secret');
        if ($expectedSecret) {
            $providedToken = $request->bearerToken() ?? $request->query('token');
            if (!$providedToken || !hash_equals($expectedSecret, $providedToken)) {
                Log::warning('TranscoderWebhook: unauthorized request', ['ip' => $request->ip()]);

                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }
        }

        // Pub/Sub push messages arrive as { "message": { "data": "<base64>", "attributes": {...} } }
        $messageData = $request->input('message.data');

        if (!$messageData) {
            Log::warning('TranscoderWebhook: received request without Pub/Sub message data');

            return response()->json(['status' => 'ignored'], 200);
        }

        $notification = json_decode(base64_decode($messageData), true);

        if (!$notification || ($notification['state'] ?? '') !== 'SUCCEEDED') {
            // Acknowledge non-success notifications to prevent Pub/Sub from retrying
            return response()->json(['status' => 'ignored'], 200);
        }

        $labels = $notification['labels'] ?? [];
        $modelConfig = self::MEDIA_MODEL_MAP[$labels['media_model'] ?? ''] ?? null;
        $mediaId = (int) ($labels['media_id'] ?? 0);
        $outputDir = base64_decode($labels['output_dir'] ?? '');
        $inputPrefix = base64_decode($labels['input_prefix'] ?? '');

        if (!$modelConfig || !$mediaId || !$outputDir || !$inputPrefix) {
            // Return 200 to acknowledge — these will never succeed on retry
            Log::error('TranscoderWebhook: missing or invalid job labels', ['labels' => $labels]);

            return response()->json(['status' => 'ignored'], 200);
        }

        $mediaClass = $modelConfig['class'];
        $filenameAttribute = $modelConfig['attribute'];

        $media = $mediaClass::find($mediaId);
        if (!$media) {
            Log::warning("TranscoderWebhook: {$mediaClass} #{$mediaId} not found, skipping.");

            return response()->json(['status' => 'ignored'], 200);
        }

        // The Transcoder outputs a single MP4 with key "sd" → "sd0000000000.mp4"
        $gcsOutputPath = rtrim($outputDir, '/').'/'.'sd0000000000.mp4';

        // Destination on the primary S3-compatible disk
        $currentPath = $media->{$filenameAttribute};
        $s3DestPath = dirname($currentPath).'/'.pathinfo($currentPath, PATHINFO_FILENAME).'.mp4';

        try {
            // Stream the transcoded MP4 from GCS staging to the S3-compatible disk
            Storage::disk('s3_clone')->writeStream(
                $s3DestPath,
                Storage::disk('gcs_staging')->readStream($gcsOutputPath)
            );

            // Clean up both the input and output files from GCS staging
            $this->deleteGcsDirectory($outputDir);
            $this->deleteGcsDirectory($inputPrefix);

            // Update the media record to point at the new MP4 file
            $media->update([$filenameAttribute => $s3DestPath]);

            Log::info('TranscoderWebhook: transcoded video stored', [
                'media_model' => $mediaClass,
                'media_id' => $mediaId,
                'path' => $s3DestPath,
            ]);
        } catch (\Throwable $e) {
            Log::error('TranscoderWebhook: failed to move transcoded file', [
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
