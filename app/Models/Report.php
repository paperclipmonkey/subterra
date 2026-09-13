<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A report raised against content or a member, or a data-protection objection.
 *
 * Reports are never deleted when they are dealt with — they are resolved, so
 * the platform keeps a record of what was reported and what was done about it.
 */
class Report extends Model
{
    /** @use HasFactory<\Database\Factories\ReportFactory> */
    use HasFactory;

    /**
     * Why something was reported. `child_safety` is listed first and handled
     * first: a report about a child cannot sit behind a queue of spam.
     * `data_objection` is raised by the platform itself rather than chosen by a
     * reporter — see SendPlaceholderUserNotice.
     */
    public const CATEGORIES = [
        'child_safety',
        'harassment',
        'privacy',
        'illegal',
        'inaccurate',
        'spam',
        'other',
        'data_objection',
    ];

    /** Categories a member may choose when reporting from the site. */
    public const USER_SELECTABLE_CATEGORIES = [
        'child_safety',
        'harassment',
        'privacy',
        'illegal',
        'inaccurate',
        'spam',
        'other',
    ];

    public const STATUSES = ['open', 'actioned', 'dismissed'];

    /** Categories that should page an admin rather than wait to be noticed. */
    public const URGENT_CATEGORIES = ['child_safety', 'illegal'];

    protected $fillable = [
        'reporter_id',
        'reportable_type',
        'reportable_id',
        'category',
        'details',
        'status',
        'handled_by',
        'handled_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isUrgent(): bool
    {
        return in_array($this->category, self::URGENT_CATEGORIES, true);
    }
}
