<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Trip extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use HasFactory;
    use HasShortId;
    use Auditable;

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

    /** @return BelongsTo<CaveSystem, $this> */
    public function system(): BelongsTo
    {
        return $this->belongsTo(CaveSystem::class, 'cave_system_id', 'id');
    }

    /** @return BelongsTo<Cave, $this> */
    public function entrance(): BelongsTo
    {
        return $this->belongsTo(Cave::class, 'entrance_cave_id', 'id');
    }

    /** @return BelongsTo<Cave, $this> */
    public function exit(): BelongsTo
    {
        return $this->belongsTo(Cave::class, 'exit_cave_id', 'id');
    }

    protected function duration(): Attribute
    {
        // Whole minutes. Carbon 3's diffInMinutes() returns a float with
        // sub-minute precision, so round to avoid fractional minutes leaking
        // into the UI (e.g. "6h 8.699999999999989m") and into summed totals.
        // Open-ended trips (no end_time) report 0 rather than diffing against
        // now(), and negative diffs are clamped to 0 so bad data can never
        // subtract from summed stats.
        return Attribute::make(
            get: fn (mixed $value, array $attributes): int => $this->start_time && $this->end_time
                ? max(0, (int) round($this->start_time->diffInMinutes($this->end_time)))
                : 0,
        );
    }

    /**
     * Whether the given cave (or its parent system) carries the Closed access
     * tag, in which case public trip reports must not be created for it.
     */
    public static function caveIsClosed(mixed $caveId): bool
    {
        $cave = Cave::with('tags', 'system.tags')->find($caveId);

        return $cave !== null && (
            $cave->tags->contains('tag', 'Closed')
            || ($cave->system && $cave->system->tags->contains('tag', 'Closed'))
        );
    }

    /** @return BelongsToMany<User, $this> */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withoutGlobalScopes();
    }

    /** @return HasMany<TripMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(TripMedia::class);
    }

    /**
     * Scope trips based on visibility for the given user.
     */
    public function scopeVisibleTo($query, $user)
    {
        return $query->where(function ($q) use ($user) {
            // Public trips are visible to everyone
            $q->where('visibility', 'public');

            if ($user) {
                // Participants can always see their trips
                $q->orWhereHas('participants', function ($participantQuery) use ($user) {
                    $participantQuery->where('user_id', $user->id);
                });

                // Private trips are visible to participants (covered above, but logic kept for structure if needed)
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
                                     $existsQuery->select(DB::raw(1))
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
