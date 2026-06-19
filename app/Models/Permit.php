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
        'photo_path',
        'photo_photographer',
        'photo_copyright',
        'conditions',
        'has_max_groups_per_day',
        'max_groups_per_day',
        'has_max_participants',
        'max_participants',
        'requires_bca',
        'has_season',
        'season_start',
        'season_end',
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
            'requires_bca' => 'boolean',
            'has_season' => 'boolean',
            'auto_approve' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The permit photo as a structured object: responsive URL/srcset plus
     * credits. Mirrors the route hero-image shape. Null when no photo is set.
     *
     * @return array{url: string|null, srcset: string|null, photographer: string|null, copyright: string|null}|null
     */
    public function getPhotoAttribute(): ?array
    {
        if (!$this->photo_path) {
            return null;
        }

        return [
            'url' => \App\Support\MediaUrl::url($this->photo_path),
            'srcset' => \App\Support\MediaUrl::srcset($this->photo_path),
            'photographer' => $this->photo_photographer,
            'copyright' => $this->photo_copyright,
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
     * Check if a date falls within the permit's season.
     * Handles wrap-around seasons (e.g. Apr–Mar).
     */
    public function isInSeason(string $date): bool
    {
        if (!$this->has_season || !$this->season_start || !$this->season_end) {
            return true;
        }

        $md = date('m-d', strtotime($date));
        $start = $this->season_start;
        $end = $this->season_end;

        if ($start <= $end) {
            // Normal season: e.g. 04-01 to 10-31
            return $md >= $start && $md <= $end;
        }

        // Wrap-around season: e.g. 04-01 to 03-10 (crosses year boundary)
        return $md >= $start || $md <= $end;
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
