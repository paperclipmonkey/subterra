<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
}
