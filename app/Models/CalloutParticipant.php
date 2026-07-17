<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

class CalloutParticipant extends Model
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'callout_id',
        'user_id',
        'name',
        'phone',
        'email',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function callout(): BelongsTo
    {
        return $this->belongsTo(Callout::class);
    }

    /**
     * Participants added via autocomplete may only carry a user_id, so notification
     * routing falls back to the linked account's contact details — an overdue-callout
     * email must not be silently dropped just because the ad-hoc field is empty.
     */
    public function routeNotificationForMail($notification = null): ?string
    {
        return $this->email ?? $this->user?->email;
    }

    /** SMS-channel equivalent of the mail fallback (see App\Channels\SmsChannel). */
    public function routeNotificationForSms($notification = null): ?string
    {
        return $this->phone ?? $this->user?->phone;
    }
}
