<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteTackle extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'description',
        'type',
        'length',
        'optional',
        'quantity',
    ];

    protected $casts = [
        'optional' => 'boolean',
        'length' => 'integer',
        'quantity' => 'integer',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}
