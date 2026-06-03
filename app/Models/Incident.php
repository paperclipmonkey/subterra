<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = str()->random(6);
            }
        });
    }

    protected $fillable = [
        'id',
        'callout_id',
        'status',
        'resolved_at',
        'incident_controller_id',
        'acknowledged_at',
        'escalated_at',
        'police_log_number',
        'last_voice_call_at',
        'voice_call_count',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'escalated_at' => 'datetime',
        'last_voice_call_at' => 'datetime',
        'voice_call_count' => 'integer',
    ];

    public function callout(): BelongsTo
    {
        return $this->belongsTo(Callout::class);
    }

    public function controller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'incident_controller_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(IncidentNote::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function resolve(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $this->callout->update(['status' => 'resolved']);
    }
}
