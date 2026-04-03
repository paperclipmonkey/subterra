<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;

class CaveSystemAnnotation extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'cave_system_id',
        'geojson',
    ];

    protected $casts = [
        'geojson' => 'array',
    ];

    public function caveSystem(): BelongsTo
    {
        return $this->belongsTo(CaveSystem::class);
    }
}
