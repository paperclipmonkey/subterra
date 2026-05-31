<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnCallShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_at',
        'end_at',
        'notified_at',
        'notify_do',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'notified_at' => 'datetime',
        'notify_do' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include shifts that cover the given datetime.
     */
    public function scopeCovering(Builder $query, Carbon|string $datetime): Builder
    {
        return $query->where('start_at', '<=', $datetime)
                     ->where('end_at', '>=', $datetime);
    }

    /**
     * Check if there is any admin on call for the given datetime.
     */
    public static function isCovered(Carbon|string $datetime): bool
    {
        return self::covering($datetime)->exists();
    }
}
