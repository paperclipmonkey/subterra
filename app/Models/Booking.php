<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;

class Booking extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use HasFactory;
    use HasShortId;
    use Auditable;

    protected $fillable = [
        'permit_id',
        'user_id',
        'date',
        'participants',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'notes',
        'conditions_accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'participants' => 'integer',
            'approved_at' => 'datetime',
            'conditions_accepted_at' => 'datetime',
        ];
    }

    public function permit(): BelongsTo
    {
        return $this->belongsTo(Permit::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Named participants with their BCA numbers (only populated for bookings
     * against permits with requires_bca enabled).
     */
    public function participantDetails(): HasMany
    {
        return $this->hasMany(BookingParticipant::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
