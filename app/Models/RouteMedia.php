<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteMedia extends Model
{
    use HasFactory;

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
}
