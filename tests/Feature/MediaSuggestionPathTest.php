<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\MediaSuggestionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Suggested-edit data comes from the client. File paths in it must be ones we
 * created under pending_edits/, or approving/rejecting the suggestion would move
 * or delete arbitrary files on the media disk.
 */
class MediaSuggestionPathTest extends TestCase
{
    private MediaSuggestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        $this->service = app(MediaSuggestionService::class);
    }

    #[Test]
    public function rejecting_a_suggestion_cannot_delete_files_outside_pending(): void
    {
        Storage::disk('media')->put('avatars/victim.jpg', 'x');

        $this->service->cleanUpPendingMedia([
            'photo_path' => 'pending_edits/../avatars/victim.jpg',
            'media' => [['data' => 'pending_edits/../avatars/victim.jpg']],
        ]);

        Storage::disk('media')->assertExists('avatars/victim.jpg');
    }

    #[Test]
    public function approving_a_suggestion_cannot_move_files_outside_pending(): void
    {
        Storage::disk('media')->put('avatars/victim.jpg', 'x');

        $this->service->promotePendingMedia(['photo_path' => 'pending_edits/../avatars/victim.jpg'], 'caves/1');

        Storage::disk('media')->assertExists('avatars/victim.jpg');
        Storage::disk('media')->assertMissing('caves/1/victim.jpg');
    }

    #[Test]
    public function genuine_pending_files_are_still_cleaned_up_and_promoted(): void
    {
        Storage::disk('media')->put('pending_edits/cave/abc_photo_path.webp', 'x');
        Storage::disk('media')->put('pending_edits/cave/def_photo_path.webp', 'y');

        $this->service->cleanUpPendingMedia(['photo_path' => 'pending_edits/cave/abc_photo_path.webp']);
        Storage::disk('media')->assertMissing('pending_edits/cave/abc_photo_path.webp');

        $result = $this->service->promotePendingMedia(['photo_path' => 'pending_edits/cave/def_photo_path.webp'], 'caves/1');
        $this->assertSame('caves/1/def_photo_path.webp', $result['photo_path']);
        Storage::disk('media')->assertExists('caves/1/def_photo_path.webp');
    }

    #[Test]
    public function non_media_uploads_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        // A real file, so the type is detected from content rather than the name.
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>');

        $this->service->savePendingMedia(
            ['photo_path' => new UploadedFile($path, 'photo.jpg', null, null, true)],
            'cave'
        );
    }
}
