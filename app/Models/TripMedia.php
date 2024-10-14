<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripMedia extends Model
{
    protected $fillable = ['trip_id', 'filename'];

    public $timestamps = false;

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}