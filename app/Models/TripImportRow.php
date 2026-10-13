<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One trip waiting to be imported.
 *
 * @property int $id
 * @property int $trip_import_id
 * @property int $row_number
 * @property int|null $source_line
 * @property string $source
 * @property string $status
 * @property string|null $cave_name_raw
 * @property string|null $entrance_name_raw
 * @property string|null $exit_name_raw
 * @property int|null $cave_system_id
 * @property int|null $entrance_cave_id
 * @property int|null $exit_cave_id
 * @property array<int, array<string, mixed>>|null $cave_candidates
 * @property \Illuminate\Support\Carbon|null $date
 * @property string|null $date_raw
 * @property string|null $start_time
 * @property int|null $duration_minutes
 * @property string|null $name
 * @property string|null $description
 * @property string|null $visibility
 * @property bool $allow_duplicate
 * @property array<int, array<string, mixed>>|null $participants
 * @property array<int, array<string, mixed>>|null $issues
 * @property int|null $trip_id
 */
class TripImportRow extends Model
{
    public const STATUS_READY = 'ready';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_FAILED = 'failed';

    /** Statuses whose rows are still waiting on the user. */
    public const PENDING_STATUSES = [self::STATUS_READY, self::STATUS_NEEDS_REVIEW, self::STATUS_DUPLICATE, self::STATUS_FAILED];

    protected $fillable = [
        'trip_import_id', 'row_number', 'source_line', 'source', 'status',
        'cave_name_raw', 'entrance_name_raw', 'exit_name_raw',
        'cave_system_id', 'entrance_cave_id', 'exit_cave_id', 'cave_candidates',
        'date', 'date_raw', 'start_time', 'duration_minutes', 'name', 'description',
        'visibility', 'allow_duplicate', 'participants', 'issues', 'trip_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'allow_duplicate' => 'boolean',
            'cave_candidates' => 'array',
            'participants' => 'array',
            'issues' => 'array',
            'duration_minutes' => 'integer',
        ];
    }

    /** @return BelongsTo<TripImport, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(TripImport::class, 'trip_import_id');
    }

    /** @return BelongsTo<CaveSystem, $this> */
    public function system(): BelongsTo
    {
        return $this->belongsTo(CaveSystem::class, 'cave_system_id');
    }

    /** @return BelongsTo<Cave, $this> */
    public function entrance(): BelongsTo
    {
        return $this->belongsTo(Cave::class, 'entrance_cave_id');
    }

    /** @return BelongsTo<Cave, $this> */
    public function exit(): BelongsTo
    {
        return $this->belongsTo(Cave::class, 'exit_cave_id');
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
