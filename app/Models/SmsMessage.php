<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    protected $fillable = [
        'provider',
        'provider_sid',
        'to_masked',
        'recipient_name',
        'user_id',
        'callout_id',
        'incident_id',
        'context',
        'status',
        'error_code',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /** Statuses Twilio reports that mean the message will not arrive. */
    public const FAILED_STATUSES = ['undelivered', 'failed', 'canceled', 'rejected'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function callout(): BelongsTo
    {
        return $this->belongsTo(Callout::class);
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /** Mask a phone number for storage/display, keeping only the last 4 digits. */
    public static function maskNumber(?string $number): ?string
    {
        if (empty($number)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $number);
        $last4 = substr((string) $digits, -4);

        return $last4 !== '' ? '•••• '.$last4 : null;
    }
}
