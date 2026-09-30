<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TripMedia extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use HasFactory;
    use Auditable;

    protected $fillable = ['trip_id', 'filename', 'original_filename', 'title', 'taken_at', 'photographer', 'copyright'];

    protected $casts = [
        'taken_at' => 'datetime',
    ];

    public $timestamps = false;

    protected static function booted(): void
    {
        // Rows are deleted when a trip is deleted or a photo is removed on edit, but
        // the files are what hold the personal data. Remove them too — after the
        // transaction commits, so a rolled-back delete never loses a photo.
        static::deleted(function (TripMedia $media) {
            $files = $media->storedFiles();
            DB::afterCommit(fn () => self::deleteUnreferencedFiles($files, $media->id));
        });
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Every file this photo owns on the media disk: the current file, the preserved
     * original, and the WebP variants the image processor writes alongside a
     * `{base}_desktop.webp` filename.
     *
     * @return list<string>
     */
    public function storedFiles(): array
    {
        $files = [$this->filename, $this->original_filename];

        if (str_ends_with((string) $this->filename, '_desktop.webp')) {
            $base = substr($this->filename, 0, -strlen('_desktop.webp'));
            $files[] = $base.'_tablet.webp';
            $files[] = $base.'_mobile.webp';
        }

        return array_values(array_unique(array_filter(
            $files,
            // External URLs (e.g. imported photos) aren't ours to delete.
            fn ($path) => is_string($path) && $path !== '' && !str_starts_with($path, 'http')
        )));
    }

    /**
     * @param  list<string>  $files
     */
    private static function deleteUnreferencedFiles(array $files, int $deletedId): void
    {
        foreach ($files as $path) {
            // Never delete a file another photo row still points at.
            $stillUsed = self::query()
                ->whereKeyNot($deletedId)
                ->where(fn ($q) => $q->where('filename', $path)->orWhere('original_filename', $path))
                ->exists();

            if ($stillUsed) {
                continue;
            }

            try {
                Storage::disk('media')->delete($path);
            } catch (\Throwable $e) {
                Log::warning("Failed to delete trip photo file {$path}: {$e->getMessage()}");
            }
        }
    }

    public function url(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => $this->filename ? \Illuminate\Support\Facades\Storage::disk('media')->url($this->filename) : null,
        );
    }
}
