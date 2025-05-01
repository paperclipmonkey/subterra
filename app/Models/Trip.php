<?php

namespace App\Models;

use App\Models\Scopes\IsActiveScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Trip extends Model
{
    protected $fillable = [
        'name',
        'description',
        'start_time',
        'end_time',
        'cave_system_id',
        'entrance_cave_id',
        'exit_cave_id',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    use HasFactory;
    public $timestamps = false;

    public function system(): BelongsTo
    {
        return $this->belongsTo(CaveSystem::class, 'cave_system_id', 'id');
    }

    public function entrance(): BelongsTo
    {
        return $this->belongsTo(Cave::class, 'entrance_cave_id', 'id');
    }

    public function exit(): BelongsTo
    {
        return $this->belongsTo(Cave::class, 'exit_cave_id', 'id');
    }

    protected function duration(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): int => $this->start_time?->diffInMinutes($this->end_time) ?: 0,
        );
    }
    
    public function participants()
    {
        return $this->belongsToMany(User::class)->withoutGlobalScope(IsActiveScope::class);
    }

    public function media()
    {
        return $this->hasMany(TripMedia::class);
    }

    /**
     * The tags that belong to the trip.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
