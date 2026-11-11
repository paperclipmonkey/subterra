<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SuggestedEdit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'suggestable_type',
        'suggestable_id',
        'original_data',
        'suggested_data',
        'status',
        'admin_comment',
        'source',
        'batch_id',
        'reasoning',
    ];

    protected $casts = [
        'original_data' => 'array',
        'suggested_data' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function suggestable(): MorphTo
    {
        return $this->morphTo();
    }
}
