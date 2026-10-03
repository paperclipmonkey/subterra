<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A logbook import staged by Pip. Each user has at most one open import; new
 * uploads and trips described in chat are added to it.
 *
 * @property int $id
 * @property string $user_id
 * @property string $status
 * @property string $default_visibility
 * @property string|null $filename
 */
class TripImport extends Model
{
    use MassPrunable;

    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DISCARDED = 'discarded';

    /** Staged rows hold people's names and trip notes — don't keep them forever. */
    public const RETENTION_DAYS = 30;

    protected $fillable = ['user_id', 'status', 'default_visibility', 'filename'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    /** @return HasMany<TripImportRow, $this> */
    public function rows(): HasMany
    {
        // No default ordering: Postgres rejects ORDER BY on the aggregate
        // queries (count/max) run through this relation.
        return $this->hasMany(TripImportRow::class);
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('updated_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
