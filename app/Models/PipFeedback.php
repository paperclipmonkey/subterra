<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipFeedback extends Model
{
    use HasFactory;

    protected $table = 'pip_feedback';

    protected $fillable = [
        'user_id',
        'rating',
        'comment',
        'transcript',
        'rated_reply',
        'reviewed',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'transcript' => 'array',
            'reviewed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
