<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'cave_system_id',
        'entrance_id',
        'exit_id',
        'name',
        'slug',
        'description',
        'duration',
        'grade',
        'hero_image',
    ];

    public function caveSystem(): BelongsTo
    {
        return $this->belongsTo(CaveSystem::class);
    }

    public function entrance(): BelongsTo
    {
        return $this->belongsTo(Cave::class, 'entrance_id');
    }

    public function exit(): BelongsTo
    {
        return $this->belongsTo(Cave::class, 'exit_id');
    }

    public function tackle(): HasMany
    {
        return $this->hasMany(RouteTackle::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(RouteMedia::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
