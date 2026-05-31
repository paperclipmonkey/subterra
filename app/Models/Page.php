<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
    protected $fillable = [
        'title',
        'slug',
        'content',
        'user_id',
        'access_count',
    ];

    protected $casts = [
        'access_count' => 'integer',
    ];
}
