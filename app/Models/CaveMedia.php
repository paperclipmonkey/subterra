<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;

class CaveMedia extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'cave_id',
        'type',
        'filename',
        'title',
        'photographer',
        'copyright',
    ];

    public function cave(): BelongsTo
    {
        return $this->belongsTo(Cave::class);
    }

    protected $appends = ['url', 'preview_url', 'poster_url'];

    public function url(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => $this->filename ? \Illuminate\Support\Facades\Storage::disk('media')->url($this->filename) : null,
        );
    }

    public function previewUrl(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => ($this->filename && $this->type === 'hero_video') ? \Illuminate\Support\Facades\Storage::disk('media')->url(str_replace(\File::extension($this->filename), 'webm', $this->filename)) : null,
        );
    }

    public function posterUrl(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => ($this->filename && $this->type === 'hero_video') ? \Illuminate\Support\Facades\Storage::disk('media')->url(str_replace(\File::extension($this->filename), 'webp', $this->filename)) : null,
        );
    }
}
