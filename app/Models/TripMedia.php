<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;

class TripMedia extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use HasFactory;
    use Auditable;

    protected $fillable = ['trip_id', 'filename', 'title', 'taken_at', 'photographer', 'copyright'];

    protected $casts = [
        'taken_at' => 'datetime',
    ];

    public $timestamps = false;

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function url(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => $this->filename ? \Illuminate\Support\Facades\Storage::disk('media')->url($this->filename) : null,
        );
    }
}
