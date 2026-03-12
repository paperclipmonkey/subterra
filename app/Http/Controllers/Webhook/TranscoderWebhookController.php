<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TranscoderWebhookController extends Controller
{
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
        $mediaModel = $this->resolveMediaModel($labels['media_model'] ?? '');
        $mediaId = (int) ($labels['media_id'] ?? 0);
        $outputDir = base64_decode($labels['output_dir'] ?? '');

        if (!$mediaModel || !$mediaId || !$outputDir) {
            Log::error('TranscoderWebhook: missing required job labels', ['labels' => $labels]);

            return response()->json(['status' => 'error', 'message' => 'Missing job labels'], 400);
        }

        $media = $mediaModel::find($mediaId);
        if (!$media) {
            Log::warning("TranscoderWebhook: {$mediaModel} #{$mediaId} not found, skipping.");

            return response()->json(['status' => 'ignored'], 200);
        }

        // The Transcoder outputs a single MP4 with key "sd" → "sd0000000000.mp4"
        $gcsOutputPath = rtrim($outputDir, '/').'/'.'sd0000000000.mp4';

        // Destination on the primary S3-compatible disk
        $s3DestPath = dirname($media->filename).'/'.pathinfo($media->filename, PATHINFO_FILENAME).'.mp4';

        try {
            // Stream the transcoded MP4 from GCS staging to the S3-compatible disk
            Storage::disk('s3_clone')->writeStream(
                $s3DestPath,
                Storage::disk('gcs_staging')->readStream($gcsOutputPath)
            );

            // Clean up the GCS staging files for this job
            $inputPrefix = 'input/';
            Storage::disk('gcs_staging')->delete($gcsOutputPath);
            $this->deleteGcsDirectory($outputDir);

            // Update the media record to point at the new MP4 file
            $filenameColumn = property_exists($media, 'filename') ? 'filename' : 'path';
            $media->update([$filenameColumn => $s3DestPath]);

            Log::info('TranscoderWebhook: transcoded video stored', [
                'media_model' => $mediaModel,
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
     * Map a snake_case label value back to a media model class.
     *
     * Only models that have a `filename` or `path` column are supported.
     */
    private function resolveMediaModel(string $label): ?string
    {
        $map = [
            'cave_media' => \App\Models\CaveMedia::class,
            'trip_media' => \App\Models\TripMedia::class,
            'route_media' => \App\Models\RouteMedia::class,
        ];

        return $map[$label] ?? null;
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
