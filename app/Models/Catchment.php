<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Catchment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'reference_id',
        'gauges',
    ];

    protected $casts = [
        'gauges' => 'array',
    ];

    public function caveSystems(): HasMany
    {
        return $this->hasMany(CaveSystem::class);
    }
}
