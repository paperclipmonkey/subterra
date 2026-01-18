<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalloutParticipant extends Model
{
    use HasFactory;

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
}
