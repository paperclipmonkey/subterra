<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;

class TripMedia extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use Auditable;

    protected $fillable = ['trip_id', 'filename'];

    public $timestamps = false;

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}