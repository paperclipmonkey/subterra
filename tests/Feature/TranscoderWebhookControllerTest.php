<?php

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveMedia;
use App\Models\TripMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranscoderWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3_clone');
        Storage::fake('gcs_staging');
    }

    private function buildPubSubPayload(array $notificationData): array
    {
        return [
            'message' => [
                'data' => base64_encode(json_encode($notificationData)),
                'attributes' => [],
            ],
            'subscription' => 'projects/test/subscriptions/transcoder',
        ];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_200_and_ignores_non_succeeded_states(): void
    {
        $payload = $this->buildPubSubPayload(['state' => 'RUNNING']);

        $response = $this->postJson('/api/webhooks/gcp/transcoder', $payload);

        $response->assertOk()->assertJson(['status' => 'ignored']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_200_when_message_data_is_missing(): void
    {
        $response = $this->postJson('/api/webhooks/gcp/transcoder', [
            'message' => ['attributes' => []],
        ]);

        $response->assertOk()->assertJson(['status' => 'ignored']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_moves_transcoded_file_and_updates_cave_media_record(): void
    {
        $cave = Cave::factory()->create();
        $media = CaveMedia::factory()->create([
            'cave_id' => $cave->id,
            'filename' => 'caves/original-video.mp4',
            'type' => 'hero_video',
        ]);

        // Place a fake transcoded MP4 on the GCS staging disk
        $outputDir = 'output/some-uuid/';
        $inputPrefix = 'input/some-input-uuid/';
        Storage::disk('gcs_staging')->put($outputDir.'sd0000000000.mp4', 'transcoded-content');
        Storage::disk('gcs_staging')->put($inputPrefix.'original-video.mp4', 'original-content');

        $payload = $this->buildPubSubPayload([
            'state' => 'SUCCEEDED',
            'labels' => [
                'media_model' => 'cave_media',
                'media_id' => (string) $media->id,
                'output_dir' => base64_encode($outputDir),
                'input_prefix' => base64_encode($inputPrefix),
            ],
        ]);

        $response = $this->postJson('/api/webhooks/gcp/transcoder', $payload);

        $response->assertOk()->assertJson(['status' => 'ok']);

        // The MP4 should now be on s3_clone
        Storage::disk('s3_clone')->assertExists('caves/original-video.mp4');

        // DB record should be updated to the new .mp4 path
        $media->refresh();
        $this->assertStringEndsWith('.mp4', $media->filename);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_moves_transcoded_file_and_updates_trip_media_record(): void
    {
        $tripMedia = TripMedia::factory()->create([
            'filename' => 'trips/trip-raw.mp4',
        ]);

        $outputDir = 'output/trip-uuid/';
        $inputPrefix = 'input/trip-input-uuid/';
        Storage::disk('gcs_staging')->put($outputDir.'sd0000000000.mp4', 'transcoded-content');
        Storage::disk('gcs_staging')->put($inputPrefix.'trip-raw.mp4', 'original-content');

        $payload = $this->buildPubSubPayload([
            'state' => 'SUCCEEDED',
            'labels' => [
                'media_model' => 'trip_media',
                'media_id' => (string) $tripMedia->id,
                'output_dir' => base64_encode($outputDir),
                'input_prefix' => base64_encode($inputPrefix),
            ],
        ]);

        $response = $this->postJson('/api/webhooks/gcp/transcoder', $payload);

        $response->assertOk()->assertJson(['status' => 'ok']);

        // s3_clone should have the new file
        Storage::disk('s3_clone')->assertExists('trips/trip-raw.mp4');

        // Both staging directories should be cleaned up
        Storage::disk('gcs_staging')->assertMissing($outputDir.'sd0000000000.mp4');
        Storage::disk('gcs_staging')->assertMissing($inputPrefix.'trip-raw.mp4');

        $tripMedia->refresh();
        $this->assertStringEndsWith('.mp4', $tripMedia->filename);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_200_when_required_labels_are_missing(): void
    {
        $payload = $this->buildPubSubPayload([
            'state' => 'SUCCEEDED',
            'labels' => [], // No labels
        ]);

        $response = $this->postJson('/api/webhooks/gcp/transcoder', $payload);

        // Returns 200 to acknowledge — invalid labels will never succeed on retry
        $response->assertOk()->assertJson(['status' => 'ignored']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_200_for_unknown_media_model_labels(): void
    {
        $payload = $this->buildPubSubPayload([
            'state' => 'SUCCEEDED',
            'labels' => [
                'media_model' => 'unknown_model',
                'media_id' => '1',
                'output_dir' => base64_encode('output/x/'),
                'input_prefix' => base64_encode('input/x/'),
            ],
        ]);

        $response = $this->postJson('/api/webhooks/gcp/transcoder', $payload);

        // Returns 200 to acknowledge — unknown models will never succeed on retry
        $response->assertOk()->assertJson(['status' => 'ignored']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_200_when_media_record_is_not_found(): void
    {
        $outputDir = 'output/gone-uuid/';
        $inputPrefix = 'input/gone-input-uuid/';
        Storage::disk('gcs_staging')->put($outputDir.'sd0000000000.mp4', 'content');

        $payload = $this->buildPubSubPayload([
            'state' => 'SUCCEEDED',
            'labels' => [
                'media_model' => 'cave_media',
                'media_id' => '99999',
                'output_dir' => base64_encode($outputDir),
                'input_prefix' => base64_encode($inputPrefix),
            ],
        ]);

        $response = $this->postJson('/api/webhooks/gcp/transcoder', $payload);

        $response->assertOk()->assertJson(['status' => 'ignored']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_requests_with_invalid_bearer_token(): void
    {
        config(['services.gcp.webhook_secret' => 'correct-secret']);

        $payload = $this->buildPubSubPayload(['state' => 'SUCCEEDED']);

        $response = $this->postJson('/api/webhooks/gcp/transcoder', $payload, [
            'Authorization' => 'Bearer wrong-secret',
        ]);

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_requests_with_valid_bearer_token(): void
    {
        config(['services.gcp.webhook_secret' => 'correct-secret']);

        $payload = $this->buildPubSubPayload(['state' => 'RUNNING']);

        $response = $this->postJson('/api/webhooks/gcp/transcoder', $payload, [
            'Authorization' => 'Bearer correct-secret',
        ]);

        $response->assertOk()->assertJson(['status' => 'ignored']);
    }
}
