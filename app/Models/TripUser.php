<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent view over the trips <-> users pivot.
 *
 * `trip_user` is deliberately keyless (see the unique-index migration), so this
 * model must not claim an auto-incrementing `id`: Eloquent would emit
 * `insert into "trip_user" (...) returning "id"`, which Postgres rejects with
 * SQLSTATE 42703. SQLite's implicit rowid hid that for as long as tests only
 * ran on SQLite.
 *
 * It is also not Auditable. Without a primary key there is no stable
 * `auditable_id` to record, and production adds/removes participants through
 * `belongsToMany::attach()`/`sync()`, which bypass Eloquent model events
 * entirely — so model-level auditing here never observed a real change.
 */
class TripUser extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'trip_user';

    /**
     * The pivot has no `id` column; see the class docblock.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'trip_id',
        'user_id',
    ];

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
