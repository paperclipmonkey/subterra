<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage; // Added for URL accessor

class CaveSystemFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'cave_system_id',
        'filename',
        'details',
        'original_filename',
        'mime_type',
        'size',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array
     */
    protected $appends = ['url'];


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
    public function getUrlAttribute(): ?string
    {
        if ($this->filename) {
            return Storage::disk('media')->url("cave_system_files/{$this->cave_system_id}/{$this->filename}");
        }
        return null;
    }
}
