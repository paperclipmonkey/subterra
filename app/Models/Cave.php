<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Trip;

class Cave extends Model
{
    use HasFactory;
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
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(CaveSystem::class,'cave_system_id', 'id');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'cave_system_id', 'cave_system_id');
    }

    public function tags () 
    {
        return $this->belongsToMany(Tag::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
