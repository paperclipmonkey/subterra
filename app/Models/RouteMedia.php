<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RouteMedia extends Model
{
    use HasFactory;

    protected $appends = ['url'];

    protected $fillable = [
        'route_id',
        'path',
        'type',
        'caption',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->path ? Storage::disk('media')->url($this->path) : null,
        );
    }
}
