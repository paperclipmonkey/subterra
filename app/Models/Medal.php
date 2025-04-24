<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medal extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'image_url',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps()->withPivot('awarded_at');
    }
}
