<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;

class Permit extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'conditions',
        'has_max_groups_per_day',
        'max_groups_per_day',
        'has_max_participants',
        'max_participants',
        'auto_approve',
        'booking_info',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'has_max_groups_per_day' => 'boolean',
            'max_groups_per_day' => 'integer',
            'has_max_participants' => 'boolean',
            'max_participants' => 'integer',
            'auto_approve' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function caves(): BelongsToMany
    {
        return $this->belongsToMany(Cave::class, 'cave_permit');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Access officers assigned to administer this permit.
     */
    public function officers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'permit_user')->withTimestamps();
    }

    /**
     * Get approved bookings for a specific date.
     */
    public function approvedBookingsForDate(string $date): HasMany
    {
        return $this->bookings()->where('status', 'approved')->whereDate('date', $date);
    }

    /**
     * Check if a date is available for a new booking.
     */
    public function isDateAvailable(string $date): bool
    {
        if (!$this->has_max_groups_per_day) {
            return true;
        }

        return $this->approvedBookingsForDate($date)->count() < $this->max_groups_per_day;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
