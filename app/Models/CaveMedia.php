<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string|null $url
 * @property string|null $preview_url
 * @property string|null $poster_url
 */
class CaveMedia extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'cave_id',
        'type',
        'filename',
        'original_filename',
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
            get: fn () => ($this->filename && $this->type === 'hero_video') ? \Illuminate\Support\Facades\Storage::disk('media')->url($this->withExtension('webm')) : null,
        );
    }

    public function posterUrl(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => ($this->filename && $this->type === 'hero_video') ? \Illuminate\Support\Facades\Storage::disk('media')->url($this->withExtension('webp')) : null,
        );
    }

    /**
     * The filename with its extension swapped. Only the trailing extension is
     * replaced — a plain str_replace on the extension would also corrupt any
     * directory or UUID segment that happens to contain the same substring.
     */
    private function withExtension(string $extension): string
    {
        return preg_replace('/\.\w+$/', '.'.$extension, $this->filename);
    }
}
