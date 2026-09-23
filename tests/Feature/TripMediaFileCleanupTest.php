<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\TripMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Deleting a trip photo (directly, by removing it on edit, by deleting the trip or
 * the account) must remove its files from storage, not just the database row.
 */
class TripMediaFileCleanupTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $files = [
        'trips/abc_desktop.webp',
        'trips/abc_tablet.webp',
        'trips/abc_mobile.webp',
        'trips/abc.jpg',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        foreach ([...$this->files, 'trips/keep.jpg'] as $file) {
            Storage::disk('media')->put($file, 'x');
        }
    }

    private function photoOn(Trip $trip): TripMedia
    {
        return TripMedia::create([
            'trip_id' => $trip->id,
            'filename' => 'trips/abc_desktop.webp',
            'original_filename' => 'trips/abc.jpg',
        ]);
    }

    private function assertPhotoFilesGone(): void
    {
        foreach ($this->files as $file) {
            Storage::disk('media')->assertMissing($file);
        }
        Storage::disk('media')->assertExists('trips/keep.jpg');
    }

    #[Test]
    public function deleting_a_trip_removes_its_photo_files_and_variants(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $trip->participants()->attach($user);
        $this->photoOn($trip);

        $this->actingAs($user)->deleteJson("/api/trips/{$trip->short_id}")->assertOk();

        $this->assertPhotoFilesGone();
        $this->assertSame(0, TripMedia::count());
    }

    #[Test]
    public function removing_a_photo_on_edit_removes_its_files(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $trip->participants()->attach($user);
        $this->photoOn($trip);

        $this->actingAs($user)
            ->putJson("/api/trips/{$trip->short_id}", ['existing_media' => []])
            ->assertOk();

        $this->assertPhotoFilesGone();
    }

    #[Test]
    public function deleting_an_account_removes_photos_on_trips_only_they_were_on(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $trip->participants()->attach($user);
        $this->photoOn($trip);

        $this->actingAs($user)->deleteJson('/api/users/me')->assertOk();

        $this->assertPhotoFilesGone();
    }

    #[Test]
    public function a_file_still_used_by_another_photo_is_kept(): void
    {
        $trip = Trip::factory()->create();
        $first = $this->photoOn($trip);
        $this->photoOn(Trip::factory()->create());

        $first->delete();

        Storage::disk('media')->assertExists('trips/abc_desktop.webp');
        Storage::disk('media')->assertExists('trips/abc.jpg');
    }

    #[Test]
    public function external_urls_are_never_deleted(): void
    {
        $media = new TripMedia(['filename' => 'https://example.com/photo.jpg']);

        $this->assertSame([], $media->storedFiles());
    }
}
