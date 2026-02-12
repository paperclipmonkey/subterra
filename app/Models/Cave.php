<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;

class Cave extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use HasFactory;
    use Auditable;

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

    protected $fillable = [
        'name',
        'description',
        'location_name',
        'location_country',
        'location_lat',
        'location_lng',
        'location_alt',
        'cave_system_id',
        'slug',
        'access_info',
        'hero_image',
        'entrance_image',
        'length',
        'depth',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'location_lat' => 'float',
        'location_lng' => 'float',
        'location_alt' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'length' => 'float',
        'depth' => 'float',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(CaveSystem::class, 'cave_system_id', 'id');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'cave_system_id', 'cave_system_id');
    }

    public function entranceTrips(): HasMany
    {
        return $this->hasMany(Trip::class, 'entrance_cave_id');
    }

    public function exitTrips(): HasMany
    {
        return $this->hasMany(Trip::class, 'exit_cave_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'cave_collection');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getCavingRegionAttribute()
    {
        return $this->tags->where('category', 'region')->first()?->tag;
    }
}
