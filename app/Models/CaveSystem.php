<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaveSystem extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'length',
        'vertical_range',
        'slug',
        'references',
    ];

    public function caves(): HasMany
    {
        return $this->hasMany(Cave::class);
    }

    public function tags () 
    {
        return $this->belongsToMany(Tag::class);
    }
}
