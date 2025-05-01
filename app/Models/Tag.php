<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Add this use statement

class Tag extends Model
{
    use HasFactory;
    protected $fillable = [
        'tag',
        'type',
        'category',
        'description',
        'assignable',
    ];

    protected $casts = [
        'assignable' => 'boolean',
    ];

    public $timestamps = false;

    /**
     * The trips that belong to the tag.
     */
    public function trips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class);
    }
}
