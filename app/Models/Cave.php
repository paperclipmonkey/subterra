<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cave extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'name',
        'length',
        'depth',
        'description',
        'location_name',
        'location_country',
        'location_lat',
        'location_lng',
        // $table->foreignId('cave_system_id')->nullable()->constrained('cave_systems', 'id');
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Cave::class);
    }
}
