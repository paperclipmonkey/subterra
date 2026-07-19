<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
