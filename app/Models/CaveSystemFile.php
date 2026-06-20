<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage; // Added for URL accessor
use OwenIt\Auditing\Auditable;

class CaveSystemFile extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'cave_system_id',
        'filename',
        'details',
        'kind',
        'visibility',
        'title',
        'photographer',
        'copyright',
        'taken_at',
        'sort_order',
        'original_filename',
        'mime_type',
        'size',
        'thumbnail_filename',
    ];

    protected $casts = [
        'taken_at' => 'date',
        'size' => 'integer',
        'sort_order' => 'integer',
    ];

    public function isImage(): bool
    {
        return $this->mime_type !== null && str_starts_with($this->mime_type, 'image/');
    }

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array
     */
    protected $appends = ['url', 'thumbnail_url'];

    /**
     * Get the cave system that owns the file.
     */
    public function caveSystem(): BelongsTo
    {
        return $this->belongsTo(CaveSystem::class);
    }

    /**
     * Get the full URL to the file.
     *
     * @return string|null
     */
    /**
     * Get the full URL to the file.
     *
     * @return string|null
     */
    public function getUrlAttribute(): ?string
    {
        if ($this->filename) {
            return Storage::disk('media')->url("cave_system_files/{$this->cave_system_id}/{$this->filename}");
        }

        return null;
    }

    /**
     * Get the full URL to the thumbnail.
     *
     * @return string|null
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail_filename) {
            return Storage::disk('media')->url("cave_system_files/{$this->cave_system_id}/{$this->thumbnail_filename}");
        }

        return null;
    }
}
