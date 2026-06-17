<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;

class Club extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use HasFactory;
    use Auditable;

    /**
     * Slug of the "Direct Individual Member" catch-all club. It collects cavers
     * who aren't members of a real club, so it deliberately has none of the
     * social features (member roster, club trips, stats) a normal club has.
     */
    public const SLUG_DIRECT_INDIVIDUAL = 'dim';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'website',
        'location',
        'is_active',
        'type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The users that belong to the club.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'club_user')
                    ->withPivot('is_admin', 'status') // Add 'status' to pivot data
                    ->withTimestamps();
    }

    /**
     * Get the count of *approved* members (users) in the club.
     * Access via $club->member_count.
     *
     * @return int
     */
    public function getMemberCountAttribute(): int
    {
        // Use the specific relationship for counting approved users
        // This ensures withCount('users') in controller counts correctly if not overridden.
        // However, direct access $club->member_count will use this accessor.
        if ($this->relationLoaded('approvedUsers')) {
            return $this->approvedUsers->count();
        }

        // Query count on the approvedUsers relationship
        return $this->approvedUsers()->count();
    }

    /**
     * Get only approved users relationship.
     */
    public function approvedUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('status', 'approved');
    }

    /**
     * Get only pending users relationship.
     */
    public function pendingUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('status', 'pending');
    }

    public function huts(): HasMany
    {
        return $this->hasMany(Hut::class);
    }

    public function reciprocalHuts(): BelongsToMany
    {
        return $this->belongsToMany(Hut::class, 'hut_reciprocal_club');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Whether this is the "Direct Individual Member" catch-all club, which has
     * its social features (member roster, club trips, stats) disabled.
     */
    public function isIndividualMembership(): bool
    {
        return $this->slug === self::SLUG_DIRECT_INDIVIDUAL;
    }
}
