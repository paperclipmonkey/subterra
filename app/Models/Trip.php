<?php

namespace App\Models;

use App\Models\Scopes\IsActiveScope;
use App\Services\TimezoneService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\DB;
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
    
    protected function timezone(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): string {
                // Get timezone from entrance cave location
                if ($this->entrance && $this->entrance->location_lat && $this->entrance->location_lng) {
                    return TimezoneService::getTimezoneFromCoordinates(
                        $this->entrance->location_lat,
                        $this->entrance->location_lng
                    );
                }
                return 'UTC';
            },
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
                
                // Club trips are visible to users who share approved clubs with any participant
                $q->orWhere(function ($clubQuery) use ($user) {
                    $clubQuery->where('visibility', 'club')
                             ->whereHas('participants', function ($participantQuery) use ($user) {
                                 $participantQuery->whereExists(function ($existsQuery) use ($user) {
                                     $existsQuery->select(\DB::raw(1))
                                               ->from('club_user as cu1')
                                               ->join('club_user as cu2', 'cu1.club_id', '=', 'cu2.club_id')
                                               ->whereColumn('cu1.user_id', 'users.id') // participant
                                               ->where('cu2.user_id', $user->id) // current user
                                               ->where('cu1.status', 'approved')
                                               ->where('cu2.status', 'approved');
                                 });
                             });
                });
            }
        });
    }
}
