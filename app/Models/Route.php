<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Route extends Model
{
    use HasFactory;

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->where('slug', $value);

        if (is_numeric($value)) {
            $query->orWhere('id', $value);
        }

        return $query->firstOrFail();
    }

    protected $fillable = [
        'cave_system_id',
        'entrance_id',
        'exit_id',
        'name',
        'slug',
        'description',
        'duration',
        'grade',
        'hero_image',
    ];

    protected function heroImage(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (!$value) {
                    return;
                }

                // If it's already a full URL, return it (backward compatibility or external images)
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    return $value;
                }

                return Storage::disk('media')->url($value);
            },
        );
    }

    public function caveSystem(): BelongsTo
    {
        return $this->belongsTo(CaveSystem::class);
    }

    public function entrance(): BelongsTo
    {
        return $this->belongsTo(Cave::class, 'entrance_id');
    }

    public function exit(): BelongsTo
    {
        return $this->belongsTo(Cave::class, 'exit_id');
    }

    public function tackle(): HasMany
    {
        return $this->hasMany(RouteTackle::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(RouteMedia::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
