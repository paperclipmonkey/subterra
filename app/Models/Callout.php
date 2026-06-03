<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Callout extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = str()->random(16);
            }
        });
    }

    protected $fillable = [
        'id',
        'user_id',
        'trip_id',
        'cave_id',
        'exit_cave_id',
        'callout_time',
        'description',
        'trip_plan',
        'car_details',
        'car_registration',
        'car_parking',
        'team_details',
        'status',
        'location_data',
        'request_data',
        'cancelled_ip',
        'cancelled_user_agent',
        'cancelled_location',
        'warned_at',
        'watchdog_registered_at',
    ];

    protected $casts = [
        'callout_time' => 'datetime',
        'warned_at' => 'datetime',
        'watchdog_registered_at' => 'datetime',
        'location_data' => 'array',
        'request_data' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Cave, $this> */
    public function cave(): BelongsTo
    {
        return $this->belongsTo(Cave::class, 'cave_id');
    }

    /** @return BelongsTo<Cave, $this> */
    public function exitCave(): BelongsTo
    {
        return $this->belongsTo(Cave::class, 'exit_cave_id');
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /** @return HasMany<CalloutParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(CalloutParticipant::class);
    }

    /** @return HasOne<Incident, $this> */
    public function incident(): HasOne
    {
        return $this->hasOne(Incident::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeTriggered(Builder $query): Builder
    {
        return $query->where('status', 'triggered');
    }

    public function scopeDueBefore(Builder $query, Carbon|string $time): Builder
    {
        return $query->where('callout_time', '<=', $time);
    }

    public function getCaveNameAttribute(): string
    {
        return $this->cave?->name ?? 'Unknown Location';
    }
}
