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
    use HasFactory, Auditable;

    public $timestamps = false;

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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
