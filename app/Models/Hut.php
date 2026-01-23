<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Hut extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'club_id',
        'location_lat',
        'location_lng',
        'amenities',
        'external_url',
        'booking_info',
        'image',
    ];

    protected $casts = [
        'amenities' => 'array',
        'location_lat' => 'float',
        'location_lng' => 'float',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function reciprocalClubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class, 'hut_reciprocal_club');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
