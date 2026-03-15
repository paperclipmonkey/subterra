<?php

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveMedia;
use App\Models\TripMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageProcessorWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3_clone');
        Storage::fake('gcs_staging');
        config(['services.gcp.webhook_secret' => null]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_stores_image_variants_and_updates_media_record(): void
    {
        $cave = Cave::factory()->create();
        $media = CaveMedia::factory()->create([
            'cave_id' => $cave->id,
            'filename' => 'caves/original-photo.jpg',
            'type' => 'hero',
        ]);

        // Put the original file on s3_clone so deletion works
        Storage::disk('s3_clone')->put('caves/original-photo.jpg', 'original-content');

        // Place processed variants on GCS staging
        $outputPrefix = 'output/some-uuid';
        Storage::disk('gcs_staging')->put($outputPrefix.'/desktop.webp', 'desktop-webp-content');
        Storage::disk('gcs_staging')->put($outputPrefix.'/tablet.webp', 'tablet-webp-content');
        Storage::disk('gcs_staging')->put($outputPrefix.'/mobile.webp', 'mobile-webp-content');
        Storage::disk('gcs_staging')->put('input/some-uuid/original-photo.jpg', 'source-content');

        $payload = [
            'status' => 'succeeded',
            'mediaModel' => 'cave_media',
            'mediaId' => $media->id,
            'sourcePath' => 'input/some-uuid/original-photo.jpg',
            'variants' => [
                ['name' => 'desktop', 'path' => $outputPrefix.'/desktop.webp', 'width' => 1920, 'height' => 1280, 'size' => 150000],
                ['name' => 'tablet', 'path' => $outputPrefix.'/tablet.webp', 'width' => 768, 'height' => 512, 'size' => 60000],
                ['name' => 'mobile', 'path' => $outputPrefix.'/mobile.webp', 'width' => 480, 'height' => 320, 'size' => 30000],
            ],
        ];

        $response = $this->postJson('/api/webhooks/gcp/image-processor', $payload);

        $response->assertOk()->assertJson(['status' => 'ok']);

        // All three variants should be on s3_clone
        Storage::disk('s3_clone')->assertExists('caves/original-photo_desktop.webp');
        Storage::disk('s3_clone')->assertExists('caves/original-photo_tablet.webp');
        Storage::disk('s3_clone')->assertExists('caves/original-photo_mobile.webp');

        // Original file should be deleted
        Storage::disk('s3_clone')->assertMissing('caves/original-photo.jpg');

        // GCS staging variants should be cleaned up
        Storage::disk('gcs_staging')->assertMissing($outputPrefix.'/desktop.webp');

        // DB record should point to the desktop variant
        $media->refresh();
        $this->assertEquals('caves/original-photo_desktop.webp', $media->filename);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_stores_trip_media_variants(): void
    {
        $tripMedia = TripMedia::factory()->create([
            'filename' => 'trips/trip-photo.png',
        ]);

        Storage::disk('s3_clone')->put('trips/trip-photo.png', 'original');

        $outputPrefix = 'output/trip-uuid';
        Storage::disk('gcs_staging')->put($outputPrefix.'/desktop.webp', 'desktop');
        Storage::disk('gcs_staging')->put($outputPrefix.'/tablet.webp', 'tablet');
        Storage::disk('gcs_staging')->put($outputPrefix.'/mobile.webp', 'mobile');

        $payload = [
            'status' => 'succeeded',
            'mediaModel' => 'trip_media',
            'mediaId' => $tripMedia->id,
            'sourcePath' => 'input/trip-uuid/trip-photo.png',
            'variants' => [
                ['name' => 'desktop', 'path' => $outputPrefix.'/desktop.webp', 'width' => 1920, 'height' => 1280, 'size' => 150000],
                ['name' => 'tablet', 'path' => $outputPrefix.'/tablet.webp', 'width' => 768, 'height' => 512, 'size' => 60000],
                ['name' => 'mobile', 'path' => $outputPrefix.'/mobile.webp', 'width' => 480, 'height' => 320, 'size' => 30000],
            ],
        ];

        $response = $this->postJson('/api/webhooks/gcp/image-processor', $payload);

        $response->assertOk()->assertJson(['status' => 'ok']);

        // Desktop variant should be stored
        Storage::disk('s3_clone')->assertExists('trips/trip-photo_desktop.webp');

        $tripMedia->refresh();
        $this->assertEquals('trips/trip-photo_desktop.webp', $tripMedia->filename);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_200_when_processing_failed(): void
    {
        $payload = [
            'status' => 'failed',
            'mediaModel' => 'cave_media',
            'mediaId' => 1,
            'error' => 'Out of memory',
        ];

        $response = $this->postJson('/api/webhooks/gcp/image-processor', $payload);

        $response->assertOk()->assertJson(['status' => 'ignored']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_200_for_unknown_model(): void
    {
        $payload = [
            'status' => 'succeeded',
            'mediaModel' => 'unknown_model',
            'mediaId' => 1,
            'variants' => [['name' => 'desktop', 'path' => 'output/x/desktop.webp', 'width' => 100, 'height' => 100, 'size' => 1000]],
        ];

        $response = $this->postJson('/api/webhooks/gcp/image-processor', $payload);

        $response->assertOk()->assertJson(['status' => 'ignored']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_200_when_media_not_found(): void
    {
        $payload = [
            'status' => 'succeeded',
            'mediaModel' => 'cave_media',
            'mediaId' => 99999,
            'variants' => [['name' => 'desktop', 'path' => 'output/x/desktop.webp', 'width' => 100, 'height' => 100, 'size' => 1000]],
        ];

        $response = $this->postJson('/api/webhooks/gcp/image-processor', $payload);

        $response->assertOk()->assertJson(['status' => 'ignored']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_requests_with_invalid_bearer_token(): void
    {
        config(['services.gcp.webhook_secret' => 'correct-secret']);

        $payload = [
            'status' => 'succeeded',
            'mediaModel' => 'cave_media',
            'mediaId' => 1,
            'variants' => [],
        ];

        $response = $this->postJson('/api/webhooks/gcp/image-processor', $payload, [
            'Authorization' => 'Bearer wrong-secret',
        ]);

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_requests_with_valid_bearer_token(): void
    {
        config(['services.gcp.webhook_secret' => 'correct-secret']);

        $payload = [
            'status' => 'failed',
            'mediaModel' => 'cave_media',
            'mediaId' => 1,
        ];

        $response = $this->postJson('/api/webhooks/gcp/image-processor', $payload, [
            'Authorization' => 'Bearer correct-secret',
        ]);

        $response->assertOk()->assertJson(['status' => 'ignored']);
    }
}
