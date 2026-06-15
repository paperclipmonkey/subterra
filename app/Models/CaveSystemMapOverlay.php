<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Auditable;

/**
 * @property int $id
 * @property int $cave_system_id
 * @property string $name
 * @property string $filename
 * @property string $original_filename
 * @property string|null $mime_type
 * @property int|null $size
 * @property array|null $bounds
 * @property float $opacity
 * @property bool $visible_by_default
 * @property int $display_order
 * @property-read string|null $url
 */
class CaveSystemMapOverlay extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'cave_system_id',
        'name',
        'filename',
        'original_filename',
        'mime_type',
        'size',
        'bounds',
        'opacity',
        'visible_by_default',
        'display_order',
    ];

    protected $casts = [
        'bounds' => 'array',
        'opacity' => 'float',
        'visible_by_default' => 'boolean',
        'display_order' => 'integer',
    ];

    protected $appends = ['url'];

    /** @return BelongsTo<CaveSystem, $this> */
    public function caveSystem(): BelongsTo
    {
        return $this->belongsTo(CaveSystem::class);
    }

    /**
     * Get the full URL to the stored GeoTIFF file.
     */
    public function getUrlAttribute(): ?string
    {
        if ($this->filename) {
            return Storage::disk('media')->url("cave_system_overlays/{$this->cave_system_id}/{$this->filename}");
        }

        return null;
    }
}
