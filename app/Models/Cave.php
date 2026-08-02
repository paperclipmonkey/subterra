<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property float|null $length
 * @property float|null $depth
 */
class Cave extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use Auditable;
    use HasFactory;
    use SoftDeletes;

    protected static function booted()
    {
        static::creating(function ($cave) {
            if (empty($cave->slug)) {
                $cave->slug = \Illuminate\Support\Str::slug($cave->name);
            }
        });
    }

    public $timestamps = false;
    protected $appends = ['caving_region'];
    // Internal/admin-only columns kept out of any default Cave serialization
    // (e.g. a trip's nested entrance/exit cave). CaveResource re-exposes
    // visibility/private_notes to data admins via explicit property access,
    // which $hidden does not affect.
    protected $hidden = ['registry', 'registry_id', 'private_notes', 'visibility', 'deleted_at'];

    protected $fillable = [
        'name',
        'description',
        'location_name',
        'location_country',
        'location_lat',
        'location_lng',
        'location_alt',
        'registry',
        'registry_id',
        'cave_system_id',
        'slug',
        'visibility',
        'access_info',
        'private_notes',
        'length',
        'depth',
        'latitude',
        'longitude',
    ];

    /** @return HasMany<CaveMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(CaveMedia::class);
    }

    /** @return HasOne<CaveMedia, $this> */
    public function heroImage(): HasOne
    {
        return $this->hasOne(CaveMedia::class)->where('type', 'hero');
    }

    /** @return HasOne<CaveMedia, $this> */
    public function entranceImage(): HasOne
    {
        return $this->hasOne(CaveMedia::class)->where('type', 'entrance');
    }

    /** @return HasOne<CaveMedia, $this> */
    public function heroVideo(): HasOne
    {
        return $this->hasOne(CaveMedia::class)->where('type', 'hero_video');
    }

    protected $casts = [
        'location_lat' => 'float',
        'location_lng' => 'float',
        'location_alt' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'length' => 'float',
        'depth' => 'float',
    ];

    /** @return BelongsTo<CaveSystem, $this> */
    public function system(): BelongsTo
    {
        return $this->belongsTo(CaveSystem::class, 'cave_system_id', 'id');
    }

    /** @return HasMany<Trip, $this> */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'cave_system_id', 'cave_system_id');
    }

    /** @return HasMany<Trip, $this> */
    public function entranceTrips(): HasMany
    {
        return $this->hasMany(Trip::class, 'entrance_cave_id');
    }

    /** @return HasMany<Trip, $this> */
    public function exitTrips(): HasMany
    {
        return $this->hasMany(Trip::class, 'exit_cave_id');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** @return BelongsToMany<Collection, $this> */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'cave_collection');
    }

    /** @return BelongsToMany<Permit, $this> */
    public function permit(): BelongsToMany
    {
        return $this->belongsToMany(Permit::class, 'cave_permit');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getCavingRegionAttribute()
    {
        if ($this->relationLoaded('tags')) {
            return optional($this->tags->firstWhere('category', 'region'))->tag;
        }

        return optional($this->tags()->where('category', 'region')->first())->tag;
    }
}
