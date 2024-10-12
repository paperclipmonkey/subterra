<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Trip extends Model
{
    protected $fillable = [
        'name',
        'description',
        'start_time',
        'end_time',
        'cave_system_id',
        'entrance_cave_id',
        'exit_cave_id',
        // $table->foreignId('cave_system_id')->nullable()->constrained('cave_systems', 'id');
    ];

    use HasFactory;
    public $timestamps = false;

    public function system(): BelongsTo
    {
        return $this->belongsTo(CaveSystem::class, 'cave_system_id', 'id');
    }

    public function entrance(): BelongsTo
    {
        return $this->belongsTo(Cave::class, 'entrance_cave_id', 'id');
    }

    public function exit(): BelongsTo
    {
        return $this->belongsTo(Cave::class, 'exit_cave_id', 'id');
    }
    
    public function participants()
    {
        return $this->belongsToMany(User::class);
    }
}
