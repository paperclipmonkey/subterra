<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApiInteraction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trackable_type',
        'trackable_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }
}
