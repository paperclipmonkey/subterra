<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;

class CaveMedia extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'cave_id',
        'type',
        'filename',
        'title',
        'photographer',
        'copyright',
    ];

    public function cave(): BelongsTo
    {
        return $this->belongsTo(Cave::class);
    }
}
