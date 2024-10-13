<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Tag extends Model
{
    protected $fillable = [
        'tag',
        'type',
        'description',
        // $table->foreignId('cave_system_id')->nullable()->constrained('cave_systems', 'id');
    ];
    public $timestamps = false;
}
