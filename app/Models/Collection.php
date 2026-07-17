<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'photo_path',
    ];

    protected $casts = [
        //
    ];

    public function getRouteKeyName()
    {
        return 'slug';
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($collection) {
            if (empty($collection->slug)) {
                $collection->slug = static::uniqueSlug(\Illuminate\Support\Str::slug($collection->name));
            }
        });

        static::updating(function ($collection) {
            if (empty($collection->slug)) {
                $collection->slug = static::uniqueSlug(\Illuminate\Support\Str::slug($collection->name), $collection->getKey());
            }
        });
    }

    /**
     * Build a unique slug by appending a numeric suffix when collisions occur.
     */
    protected static function uniqueSlug(string $base, mixed $ignoreId = null): string
    {
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            ++$suffix;
        }

        return $slug;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function caves(): BelongsToMany
    {
        return $this->belongsToMany(Cave::class, 'cave_collection')
            ->withPivot(['description', 'sort_order'])
            ->orderByPivot('sort_order');
    }
}
