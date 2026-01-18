<?php

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
        'is_official',
    ];

    protected $casts = [
        'is_official' => 'boolean',
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
                $collection->slug = \Illuminate\Support\Str::slug($collection->name);
            }
        });
        
        static::updating(function ($collection) {
             if (empty($collection->slug)) {
                $collection->slug = \Illuminate\Support\Str::slug($collection->name);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function caves(): BelongsToMany
    {
        return $this->belongsToMany(Cave::class, 'cave_collection');
    }
}
