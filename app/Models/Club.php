<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Club extends Model
{
    use HasFactory;

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
        'is_enabled',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_enabled' => 'boolean',
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
     * Access via $club->member_count
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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
