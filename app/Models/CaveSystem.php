<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Auditable;

/**
 * @property-read \App\Models\Catchment|null $catchment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Cave> $caves
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tag> $tags
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CaveSystemFile> $files
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Route> $routes
 * @property-read \App\Models\CaveSystemAnnotation|null $annotation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CaveSystemMapOverlay> $mapOverlays
 */
class CaveSystem extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use HasFactory;
    use Auditable;

    public $timestamps = false;

    public function resolveRouteBinding($value, $field = null): static
    {
        if (is_numeric($value)) {
            return $this->where('id', $value)->firstOrFail();
        }

        return $this->where('slug', $value)->firstOrFail();
    }

    protected $fillable = [
        'name',
        'description',
        'length',
        'vertical_range',
        'slug',
        'references',
        'catchment_id',
    ];

    /** @return BelongsTo<Catchment, $this> */
    public function catchment(): BelongsTo
    {
        return $this->belongsTo(Catchment::class);
    }

    /** @return HasMany<Cave, $this> */
    public function caves(): HasMany
    {
        return $this->hasMany(Cave::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Get the files associated with the cave system.
     *
     * @return HasMany<CaveSystemFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(CaveSystemFile::class);
    }

    /** @return HasMany<Route, $this> */
    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    /** @return HasOne<CaveSystemAnnotation, $this> */
    public function annotation(): HasOne
    {
        return $this->hasOne(CaveSystemAnnotation::class);
    }

    /** @return HasMany<CaveSystemMapOverlay, $this> */
    public function mapOverlays(): HasMany
    {
        return $this->hasMany(CaveSystemMapOverlay::class)
            ->orderBy('display_order')
            ->orderBy('id');
    }
}
