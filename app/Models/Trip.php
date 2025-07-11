<?php

namespace App\Models;

use App\Models\Scopes\IsActiveScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use OwenIt\Auditing\Auditable;

class Trip extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'description',
        'start_time',
        'end_time',
        'cave_system_id',
        'entrance_cave_id',
        'exit_cave_id',
        'visibility',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

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
     * Scope trips based on visibility for the given user
     */
    public function scopeVisibleTo($query, $user)
    {
        return $query->where(function ($q) use ($user) {
            // Public trips are visible to everyone
            $q->where('visibility', 'public');
            
            if ($user) {
                // Private trips are visible to participants
                $q->orWhere(function ($privateQuery) use ($user) {
                    $privateQuery->where('visibility', 'private')
                                ->whereHas('participants', function ($participantQuery) use ($user) {
                                    $participantQuery->where('user_id', $user->id);
                                });
                });
                
                // Club trips are visible to users who share clubs with any participant
                $q->orWhere(function ($clubQuery) use ($user) {
                    $clubQuery->where('visibility', 'club')
                             ->whereHas('participants.clubs', function ($clubsQuery) use ($user) {
                                 $clubsQuery->whereIn('clubs.id', $user->clubs()->pluck('clubs.id'));
                             });
                });
            }
        });
    }
}
